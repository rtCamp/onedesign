<?php
/**
 * Admin settings screen unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Settings
 */

declare(strict_types = 1);

namespace OneDesign\Tests\Integration\Modules\Settings;

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
