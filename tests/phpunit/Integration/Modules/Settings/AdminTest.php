<?php
/**
 * Admin settings screen unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Settings
 */

declare(strict_types = 1);

namespace OneDesign\Tests\Integration\Modules\Settings;

use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Settings\Admin;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the settings admin screen.
 */
#[CoversClass( \OneDesign\Modules\Settings\Admin::class )]
final class AdminTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		set_current_screen( 'dashboard' );

		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

		$this->remove_menu_entries();

		parent::tearDown();
	}

	/**
	 * Tests that the default submenu is removed on every site type.
	 */
	public function test_remove_default_submenu_removes_submenu_when_conditions_met(): void {
		$admin = new Admin();
		$admin->add_admin_menu();
		$admin->add_submenu();

		// The menu slug has no screen of its own, so a governing site with brand sites drops it too.
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		update_option(
			Settings::OPTION_GOVERNING_SHARED_SITES,
			[
				[
					'url'     => 'https://example.com',
					'name'    => 'Test',
					'api_key' => '',
				],
			]
		);
		$admin->remove_default_submenu();

		$this->assertArrayHasKey( Admin::MENU_SLUG, $GLOBALS['submenu'] );
		$this->assertFalse( $this->submenu_contains_slug( Admin::MENU_SLUG, Admin::MENU_SLUG ) );
	}

	/**
	 * Ensures the screen callback renders the settings mount point.
	 */
	public function test_screen_callback_outputs_expected_html(): void {
		ob_start();
		( new Admin() )->screen_callback();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'onedesign-settings-page', $output );
		$this->assertStringContainsString( 'wrap', $output );
	}

	/**
	 * Ensures the plugin action links include a settings link.
	 */
	public function test_add_action_links_appends_settings_link(): void {
		$links = ( new Admin() )->add_action_links( [] );

		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'Settings', $links[0] );
		$this->assertStringContainsString( 'admin.php?page=' . Admin::SCREEN_ID, $links[0] );

		// Test that the method does not error when given invalid input.
		$this->setExpectedIncorrectUsage( Admin::class . '::add_action_links' );

		( new Admin() )->add_action_links( 'invalid' );
	}

	/**
	 * Ensures the setup notice shows on the plugins and dashboard screens when the site type is unset.
	 *
	 * @param string $screen Screen to render the notice on.
	 */
	#[DataProvider( 'setup_notice_screen_provider' )]
	public function test_render_notices_shows_setup_notice_when_site_type_missing( string $screen ): void {
		set_current_screen( $screen );

		$output = $this->get_notices();

		$this->assertStringContainsString( 'OneDesign needs to be set up', $output );
		$this->assertStringContainsString( 'admin.php?page=' . Admin::SCREEN_ID, $output );
	}

	/**
	 * Provides screens that show the setup notice.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function setup_notice_screen_provider(): array {
		return [
			'plugins'   => [ 'plugins' ],
			'dashboard' => [ 'dashboard' ],
		];
	}

	/**
	 * Ensures the setup notice is skipped when it doesn't apply.
	 */
	public function test_render_notices_skips_setup_notice_when_not_applicable(): void {
		// Unrelated screen.
		set_current_screen( 'edit-post' );
		$this->assertStringNotContainsString( 'needs to be set up', $this->get_notices() );

		// The settings page, which shows the picker itself.
		set_current_screen( Admin::SETTINGS_HOOK );
		$this->assertStringNotContainsString( 'needs to be set up', $this->get_notices() );

		// Already set up.
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		set_current_screen( 'plugins' );
		$this->assertStringNotContainsString( 'needs to be set up', $this->get_notices() );

		// No permission.
		delete_option( Settings::OPTION_SITE_TYPE );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertStringNotContainsString( 'needs to be set up', $this->get_notices() );
	}

	/**
	 * A network admin on a network-activated governing site gets the other sites they can add, and no one else or other screen gets them.
	 */
	public function test_the_settings_screen_lists_the_sites_a_network_admin_can_add(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		update_site_option( 'active_sitewide_plugins', [ plugin_basename( ONEDESIGN_FILE ) => time() ] );
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$new_site_id = self::factory()->blog->create();

		$unconnected_brand_id = self::factory()->blog->create();
		update_blog_option( $unconnected_brand_id, Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$connected_brand_id = self::factory()->blog->create();
		switch_to_blog( $connected_brand_id );
		$this->set_up_brand_site();
		restore_current_blog();

		$governing_id = self::factory()->blog->create();
		update_blog_option( $governing_id, Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$this->assertArrayNotHasKey( 'multisites', $this->get_settings_screen_data() );

		$network_admin_id = self::factory()->user->create();
		grant_super_admin( $network_admin_id );
		wp_set_current_user( $network_admin_id );

		$data       = $this->get_settings_screen_data();
		$multisites = $data['multisites'];

		$this->assertSame( [ (string) $new_site_id, (string) $unconnected_brand_id ], array_column( $multisites, 'id' ) );
		$this->assertSame( (string) get_current_blog_id(), $data['currentSiteId'] );
		$this->assertArrayNotHasKey( 'multisites', Assets::get_localized_data() );
	}

	/**
	 * Enqueues the settings screen and gets the data it localizes.
	 *
	 * @return array<string,mixed>
	 */
	private function get_settings_screen_data(): array {
		( new Assets() )->register_assets();
		wp_scripts()->add_data( Assets::SETTINGS_SCRIPT_HANDLE, 'data', '' );
		( new Admin() )->enqueue_scripts( Admin::SETTINGS_HOOK );

		preg_match( '/var OneDesignSettings = (.*);/', (string) wp_scripts()->get_data( Assets::SETTINGS_SCRIPT_HANDLE, 'data' ), $matches );

		return json_decode( $matches[1], true );
	}

	/**
	 * Captures the output of the admin notices.
	 */
	private function get_notices(): string {
		ob_start();
		( new Admin() )->render_notices();

		return (string) ob_get_clean();
	}

	/**
	 * Checks whether a submenu slug is present for a menu slug.
	 *
	 * @param string $menu_slug    Parent menu slug.
	 * @param string $submenu_slug Submenu slug.
	 */
	private function submenu_contains_slug( string $menu_slug, string $submenu_slug ): bool {
		foreach ( (array) ( $GLOBALS['submenu'][ $menu_slug ] ?? [] ) as $submenu_item ) {
			if ( isset( $submenu_item[2] ) && $submenu_slug === $submenu_item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes menu entries registered by this test.
	 */
	private function remove_menu_entries(): void {
		foreach ( (array) ( $GLOBALS['menu'] ?? [] ) as $index => $menu_item ) {
			if ( isset( $menu_item[2] ) && Admin::MENU_SLUG === $menu_item[2] ) {
				unset( $GLOBALS['menu'][ $index ] );
			}
		}

		unset( $GLOBALS['submenu'][ Admin::MENU_SLUG ] );
	}
}
