<?php
/**
 * Tests for changing the site type over REST.
 *
 * @package OneDesign\Tests\Integration\Modules\Settings
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Settings;

use OneDesign\Main;
use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * The site type only changes through core's /wp/v2/settings, which refuses values that aren't a site type.
 */
#[CoversClass( Settings::class )]
final class SiteTypeTest extends TestCase {
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

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}
		wp_set_current_user( $admin_id );

		// The settings, and every plugin route, as the plugin registers them.
		( new Settings() )->register_hooks();
		foreach ( ( new \ReflectionClassConstant( Main::class, 'REGISTRABLE_CLASSES' ) )->getValue() as $class_name ) {
			if ( is_subclass_of( $class_name, Abstract_REST_Controller::class ) ) {
				( new $class_name() )->register_hooks();
			}
		}

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
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
	 * A value that isn't a site type is refused, and the site keeps its type.
	 */
	public function test_an_invalid_site_type_is_refused(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_param( Settings::OPTION_SITE_TYPE, 'bogus' );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
		$this->assertSame( Settings::SITE_TYPE_GOVERNING, Settings::get_site_type() );
	}

	/**
	 * There's no OneDesign route that takes any value as the site type.
	 */
	public function test_no_plugin_route_sets_the_site_type(): void {
		$request = new WP_REST_Request( 'POST', '/onedesign/v1/site-type' );
		$request->set_param( 'site_type', 'bogus' );

		$this->assertSame( 404, $this->server->dispatch( $request )->get_status() );
		$this->assertSame( Settings::SITE_TYPE_GOVERNING, Settings::get_site_type() );
	}
}
