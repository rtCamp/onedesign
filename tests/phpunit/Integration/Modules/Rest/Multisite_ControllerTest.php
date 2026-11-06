<?php
/**
 * Tests for Multisite_Controller.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Multisite_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use OneDesign\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Tests for the /multisite routes, on a network where blog 1 governs brand A and brand B has no type yet.
 */
#[CoversClass( Multisite_Controller::class )]
final class Multisite_ControllerTest extends TestCase {
	/**
	 * REST server.
	 */
	private ?\WP_REST_Server $server;

	/**
	 * The governing site's blog id.
	 */
	private int $governing_id;

	/**
	 * Brand A's blog id, a brand site of the governing site.
	 */
	private int $brand_a_id;

	/**
	 * Brand A's URL.
	 */
	private string $brand_a_url;

	/**
	 * Brand B's blog id, a site on the network with no OneDesign type yet, whose name core stores HTML-escaped.
	 */
	private int $brand_b_id;

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		$this->skipWithoutMultisite();

		update_site_option( 'active_sitewide_plugins', [ plugin_basename( ONEDESIGN_FILE ) => time() ] );

		$this->governing_id = get_current_blog_id();
		$this->brand_a_id   = self::factory()->blog->create();
		$this->brand_b_id   = self::factory()->blog->create( [ 'title' => 'Sales & Marketing' ] );
		$this->brand_a_url  = Utils::normalize_url( get_blog_option( $this->brand_a_id, 'home' ) );

		( new Settings() )->register_hooks();
		( new Multisite_Controller() )->register_hooks();

		$this->server = $this->init_rest_server();

		$this->set_up_governing_site(
			[
				[
					'id'      => (string) $this->brand_a_id,
					'name'    => 'Brand A',
					'url'     => $this->brand_a_url,
					'api_key' => 'brand-a-key',
				],
			]
		);

		switch_to_blog( $this->brand_a_id );
		$this->set_up_brand_site();
		restore_current_blog();

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );
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
	 * The routes are only there when OneDesign is network-activated.
	 */
	public function test_the_routes_need_onedesign_to_be_network_activated(): void {
		update_site_option( 'active_sitewide_plugins', [] );
		remove_all_actions( 'rest_api_init' );

		( new Multisite_Controller() )->register_hooks();

		$routes = $this->init_rest_server()->get_routes();

		$this->assertArrayNotHasKey( '/onedesign/v1/multisite/brand-sites', $routes );
		$this->assertArrayNotHasKey( '/onedesign/v1/multisite/governing-sites', $routes );
	}

	/**
	 * Even the governing site's own administrator can't make governing sites or add network sites.
	 */
	public function test_a_site_admin_cannot_change_network_sites(): void {
		$user_id = self::factory()->user->create();
		add_user_to_blog( $this->governing_id, $user_id, 'administrator' );
		wp_set_current_user( $user_id );

		$this->assertSame( 403, $this->add_brand_sites( $this->governing_id, [ $this->brand_b_id ] )->get_status() );
		$this->assertSame( 403, $this->add_governing_site( $this->brand_b_id )->get_status() );
		$this->assertSame( [ $this->brand_a_url ], array_keys( Settings::get_shared_sites() ) );
		$this->assertNull( get_blog_option( $this->brand_b_id, Settings::OPTION_SITE_TYPE, null ) );
	}

	/**
	 * A site with no type becomes a governing site.
	 */
	public function test_a_site_with_no_type_becomes_a_governing_site(): void {
		$response = $this->add_governing_site( $this->brand_b_id );

		$this->assertSame( 204, $response->get_status() );
		$this->assertSame( Settings::SITE_TYPE_GOVERNING, get_blog_option( $this->brand_b_id, Settings::OPTION_SITE_TYPE ) );
	}

	/**
	 * A site that has a type, or isn't on the network, isn't made a governing site, and nothing changes.
	 */
	public function test_a_site_with_a_type_is_not_made_a_governing_site(): void {
		$cases = [
			'a governing site' => [ $this->governing_id, 'onedesign_site_has_type' ],
			'a brand site'     => [ $this->brand_a_id, 'onedesign_site_has_type' ],
			'a missing site'   => [ $this->brand_b_id + 100, 'onedesign_site_not_found' ],
		];

		foreach ( $cases as $name => [ $site_id, $code ] ) {
			$response = $this->add_governing_site( $site_id );

			$this->assertSame( 400, $response->get_status(), $name );
			$this->assertSame( $code, $response->as_error()->get_error_code(), $name );
		}

		$this->assertSame( Settings::SITE_TYPE_CONSUMER, get_blog_option( $this->brand_a_id, Settings::OPTION_SITE_TYPE ) );
	}

	/**
	 * A request with no site ID is refused, and the site it came to stays typeless.
	 */
	public function test_a_governing_site_needs_a_site_id(): void {
		delete_option( Settings::OPTION_SITE_TYPE );

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/multisite/governing-sites' );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
		$this->assertNull( Settings::get_site_type() );
	}

	/**
	 * A site with no type becomes a brand site of the governing site, next to the brands it had.
	 */
	public function test_sites_are_added_to_the_governing_site(): void {
		$response = $this->add_brand_sites( $this->governing_id, [ $this->brand_b_id ] );

		$this->assertSame( 200, $response->get_status() );

		switch_to_blog( $this->brand_b_id );
		$brand_b_url = Utils::get_current_site_url();
		$site_type   = Settings::get_site_type();
		$api_key     = Settings::get_api_key();
		$parent_url  = Settings::get_parent_site_url();
		restore_current_blog();

		$this->assertSame( Settings::SITE_TYPE_CONSUMER, $site_type );
		$this->assertSame( untrailingslashit( get_blog_option( $this->governing_id, 'home' ) ), $parent_url );

		$shared_sites = Settings::get_shared_sites();
		$this->assertSame( [ $this->brand_a_url, $brand_b_url ], array_keys( $shared_sites ) );
		$this->assertSame( 'brand-a-key', $shared_sites[ $this->brand_a_url ]['api_key'] );
		$this->assertSame(
			[
				'api_key' => $api_key,
				'id'      => (string) $this->brand_b_id,
				'logo'    => '',
				'logo_id' => 0,
				'name'    => 'Sales & Marketing',
				'url'     => $brand_b_url,
			],
			$shared_sites[ $brand_b_url ]
		);

		// The browser gets the stored list, without its keys.
		$this->assertSame( [ (string) $this->brand_a_id, (string) $this->brand_b_id ], array_column( $response->get_data()['shared_sites'], 'id' ) );
		$this->assertStringNotContainsString( 'brand-a-key', (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Sites are added to the governing site the request names, not to the site the request came to.
	 */
	public function test_sites_are_added_to_the_chosen_governing_site(): void {
		$other_governing_id = self::factory()->blog->create();

		switch_to_blog( $other_governing_id );
		$this->set_up_governing_site( [] );
		restore_current_blog();

		$response = $this->add_brand_sites( $other_governing_id, [ $this->brand_b_id ] );

		switch_to_blog( $other_governing_id );
		$shared_sites = Settings::get_shared_sites();
		restore_current_blog();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ (string) $this->brand_b_id ], array_column( $shared_sites, 'id' ) );
		$this->assertSame( [ (string) $this->brand_b_id ], array_column( $response->get_data()['shared_sites'], 'id' ) );
		$this->assertSame( Settings::SITE_TYPE_CONSUMER, get_blog_option( $this->brand_b_id, Settings::OPTION_SITE_TYPE ) );
		$this->assertSame( untrailingslashit( get_blog_option( $other_governing_id, 'home' ) ), get_blog_option( $this->brand_b_id, Settings::OPTION_CONSUMER_PARENT_SITE_URL ) );
		$this->assertSame( [ $this->brand_a_url ], array_keys( Settings::get_shared_sites() ) );
	}

	/**
	 * A brand site that isn't connected to a governing site can be added.
	 */
	public function test_a_brand_site_with_no_governing_site_can_be_added(): void {
		update_blog_option( $this->brand_b_id, Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$response = $this->add_brand_sites( $this->governing_id, [ $this->brand_b_id ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( (string) $this->brand_b_id, array_column( Settings::get_shared_sites(), 'id' ) );
	}

	/**
	 * Sites are only added to a governing site on the network, and nothing is written otherwise.
	 */
	public function test_sites_are_not_added_without_a_governing_site(): void {
		$typeless_id = self::factory()->blog->create();

		$cases = [
			'no governing site'   => [ 'site_ids' => [ $this->brand_b_id ] ],
			'a site with no type' => [
				'governing_site_id' => $typeless_id,
				'site_ids'          => [ $this->brand_b_id ],
			],
			'a brand site'        => [
				'governing_site_id' => $this->brand_a_id,
				'site_ids'          => [ $this->brand_b_id ],
			],
			'a missing site'      => [
				'governing_site_id' => $typeless_id + 100,
				'site_ids'          => [ $this->brand_b_id ],
			],
		];

		foreach ( $cases as $name => $body ) {
			$request = new WP_REST_Request( 'POST', '/onedesign/v1/multisite/brand-sites' );
			$request->set_body_params( $body );

			$this->assertSame( 400, $this->server->dispatch( $request )->get_status(), $name );
		}

		$this->assertFalse( get_blog_option( $typeless_id, Settings::OPTION_GOVERNING_SHARED_SITES ) );
		$this->assertFalse( get_blog_option( $this->brand_a_id, Settings::OPTION_GOVERNING_SHARED_SITES ) );
		$this->assertSame( [ $this->brand_a_url ], array_keys( Settings::get_shared_sites() ) );
		$this->assertNull( get_blog_option( $this->brand_b_id, Settings::OPTION_SITE_TYPE, null ) );
		$this->assertNull( get_blog_option( $typeless_id, Settings::OPTION_SITE_TYPE, null ) );
	}

	/**
	 * Governing sites, brands connected to a governing site and missing sites are refused, and the sites sent with them aren't added.
	 */
	public function test_sites_that_cannot_be_brand_sites_are_refused(): void {
		$other_governing_id = self::factory()->blog->create();
		update_blog_option( $other_governing_id, Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$cases = [
			'the governing site'     => [ $this->governing_id, 'onedesign_site_cannot_be_brand' ],
			'another governing site' => [ $other_governing_id, 'onedesign_site_cannot_be_brand' ],
			'a connected brand'      => [ $this->brand_a_id, 'onedesign_site_cannot_be_brand' ],
			'a missing site'         => [ $other_governing_id + 100, 'onedesign_site_not_found' ],
		];

		foreach ( $cases as $name => [ $site_id, $code ] ) {
			$response = $this->add_brand_sites( $this->governing_id, [ $this->brand_b_id, $site_id ] );

			$this->assertSame( 400, $response->get_status(), $name );
			$this->assertSame( $code, $response->as_error()->get_error_code(), $name );
			$this->assertSame( [ $this->brand_a_url ], array_keys( Settings::get_shared_sites() ), $name );
			$this->assertNull( get_blog_option( $this->brand_b_id, Settings::OPTION_SITE_TYPE, null ), $name );
		}

		$this->assertSame( Settings::SITE_TYPE_GOVERNING, get_blog_option( $other_governing_id, Settings::OPTION_SITE_TYPE ) );
		$this->assertSame( 'https://governing.example.com', get_blog_option( $this->brand_a_id, Settings::OPTION_CONSUMER_PARENT_SITE_URL ) );
	}

	/**
	 * A request with no site IDs is refused.
	 */
	public function test_brand_sites_need_site_ids(): void {
		$request = new WP_REST_Request( 'POST', '/onedesign/v1/multisite/brand-sites' );
		$request->set_body_params( [ 'governing_site_id' => $this->governing_id ] );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Creates a REST server and registers its routes.
	 */
	private function init_rest_server(): \WP_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		return $wp_rest_server;
	}

	/**
	 * POSTs /multisite/governing-sites.
	 *
	 * @param int $site_id The site to make a governing site.
	 */
	private function add_governing_site( int $site_id ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/onedesign/v1/multisite/governing-sites' );
		$request->set_body_params( [ 'site_id' => $site_id ] );

		return $this->server->dispatch( $request );
	}

	/**
	 * POSTs /multisite/brand-sites.
	 *
	 * @param int   $governing_site_id The governing site to add them to.
	 * @param int[] $site_ids          The sites to add.
	 */
	private function add_brand_sites( int $governing_site_id, array $site_ids ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/onedesign/v1/multisite/brand-sites' );
		$request->set_body_params(
			[
				'governing_site_id' => $governing_site_id,
				'site_ids'          => $site_ids,
			]
		);

		return $this->server->dispatch( $request );
	}
}
