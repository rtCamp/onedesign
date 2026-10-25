<?php
/**
 * Settings admin screen unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Settings
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Tests reset WP globals as fixtures.
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Settings;

use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Settings\Admin;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the settings admin screen.
 */
#[CoversClass( Admin::class )]
final class AdminTest extends TestCase {
	/**
	 * Localized data from before this fixture replaced the request cache.
	 *
	 * @var array<string,mixed>
	 */
	private array $original_localized_data = [];

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->preserve_globals( [ 'wp_scripts', 'wp_styles', 'menu', 'submenu' ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$cache                         = new \ReflectionProperty( Assets::class, 'localized_data' );
		$this->original_localized_data = $cache->isInitialized() ? $cache->getValue() : [];
		$cache->setValue( null, [] );

		$GLOBALS['wp_scripts'] = new \WP_Scripts();
		$GLOBALS['wp_styles']  = new \WP_Styles();
		foreach ( [ Assets::SETTINGS_SCRIPT_HANDLE, Assets::ONBOARDING_SCRIPT_HANDLE ] as $handle ) {
			wp_register_script( $handle, 'https://example.com/' . $handle . '.js', [], '1', true );
			wp_register_style( $handle, 'https://example.com/' . $handle . '.css', [], '1' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['current_screen'], $GLOBALS['submenu'][ Admin::MENU_SLUG ] );
		$GLOBALS['menu'] = [];

		( new \ReflectionProperty( Assets::class, 'localized_data' ) )->setValue( null, $this->original_localized_data );

		parent::tearDown();
	}

	/**
	 * The menu exposes only the Settings page; the default duplicate submenu is removed.
	 */
	public function test_admin_menu_registers_settings_page_only(): void {
		$admin = new Admin();
		$admin->add_admin_menu();
		$admin->add_submenu();

		$this->assertContains( Admin::MENU_SLUG, array_column( $GLOBALS['menu'], 2 ) );
		$this->assertSame( [ Admin::MENU_SLUG, Admin::SCREEN_ID ], array_column( $GLOBALS['submenu'][ Admin::MENU_SLUG ], 2 ) );

		$admin->remove_default_submenu();

		$this->assertSame( [ Admin::SCREEN_ID ], array_values( array_column( $GLOBALS['submenu'][ Admin::MENU_SLUG ], 2 ) ) );
	}

	/**
	 * The settings page renders the React mount point.
	 */
	public function test_screen_callback_renders_mount_point(): void {
		$this->expectOutputRegex( '/<div id="onedesign-settings-page"><\/div>/' );

		( new Admin() )->screen_callback();
	}

	/**
	 * A Settings link is appended to the plugin action links.
	 */
	public function test_add_action_links_appends_settings_link(): void {
		$links = ( new Admin() )->add_action_links( [ '<a href="#">Deactivate</a>' ] );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'admin.php?page=' . Admin::SCREEN_ID, $links[1] );
	}

	/**
	 * Non-array input from other plugins is replaced rather than causing a fatal.
	 *
	 * @expectedIncorrectUsage OneDesign\Modules\Settings\Admin::add_action_links
	 */
	public function test_add_action_links_recovers_from_invalid_input(): void {
		$links = ( new Admin() )->add_action_links( 'not-an-array' );

		$this->assertCount( 1, $links );
	}

	/**
	 * Before a site type is chosen, the plugins screen shows the onboarding modal.
	 */
	public function test_onboarding_modal_shown_until_site_type_is_set(): void {
		set_current_screen( 'plugins' );
		$admin = new Admin();

		$classes = $admin->add_body_classes( 'existing' );
		$this->assertStringContainsString( 'onedesign-site-selection-modal', $classes );
		$this->assertStringContainsString( 'onedesign-missing-brand-sites', $classes );

		$admin->enqueue_scripts( 'plugins.php' );
		$this->assertTrue( wp_script_is( Assets::ONBOARDING_SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_script_is( Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );

		ob_start();
		$admin->inject_site_selection_modal();
		$this->assertStringContainsString( 'id="onedesign-site-selection-modal"', (string) ob_get_clean() );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		Settings::set_shared_sites(
			[
				[
					'name' => 'Brand',
					'url'  => 'https://brand.example',
				],
			]
		);

		$this->assertSame( 'existing', $admin->add_body_classes( 'existing' ) );

		ob_start();
		$admin->inject_site_selection_modal();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The onboarding modal is not shown on unrelated admin screens.
	 */
	public function test_onboarding_modal_hidden_on_unrelated_screens(): void {
		set_current_screen( 'edit-post' );
		$admin = new Admin();

		$this->assertStringNotContainsString( 'onedesign-site-selection-modal', $admin->add_body_classes( '' ) );

		$admin->enqueue_scripts( 'edit.php' );
		$this->assertFalse( wp_script_is( Assets::ONBOARDING_SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * The settings page enqueues the settings bundle, and the media library only on governing sites.
	 */
	public function test_enqueue_scripts_on_settings_page(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		set_current_screen( 'toplevel_page_' . Admin::SCREEN_ID );

		( new Admin() )->enqueue_scripts( 'onedesign_page_' . Admin::SCREEN_ID );

		$this->assertTrue( wp_script_is( Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );
		$localized = (string) wp_scripts()->get_data( Assets::SETTINGS_SCRIPT_HANDLE, 'data' );
		$this->assertStringContainsString( 'var OneDesignSettings', $localized );
		$this->assertStringContainsString( '"apiKey":"' . Settings::get_api_key() . '"', $localized );
		$this->assertSame( 0, did_action( 'wp_enqueue_media' ) );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		( new Admin() )->enqueue_settings_scripts();

		$this->assertSame( 1, did_action( 'wp_enqueue_media' ) );
	}
}
