<?php
/**
 * Assets unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Core
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Tests reset WP globals as fixtures.
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Core;

use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Post_Types\Pattern;
use OneDesign\Modules\Post_Types\Template;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for asset registration and enqueueing.
 */
#[CoversClass( Assets::class )]
final class AssetsTest extends TestCase {
	/**
	 * Temporary plugin directory holding fixture build files.
	 */
	private string $fixture_dir;

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

		$this->preserve_globals( [ 'wp_scripts', 'wp_styles' ] );

		$GLOBALS['wp_scripts'] = new \WP_Scripts();
		$GLOBALS['wp_styles']  = new \WP_Styles();

		$cache                         = new \ReflectionProperty( Assets::class, 'localized_data' );
		$this->original_localized_data = $cache->isInitialized() ? $cache->getValue() : [];
		$cache->setValue( null, [] );

		$this->fixture_dir = trailingslashit( get_temp_dir() ) . uniqid( 'onedesign-assets-', true ) . '/';
		wp_mkdir_p( $this->fixture_dir . 'build' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		foreach ( (array) glob( $this->fixture_dir . 'build/*' ) as $file ) {
			unlink( (string) $file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
		rmdir( $this->fixture_dir . 'build' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
		rmdir( $this->fixture_dir ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir

		unset( $GLOBALS['current_screen'] );

		( new \ReflectionProperty( Assets::class, 'localized_data' ) )->setValue( null, $this->original_localized_data );

		parent::tearDown();
	}

	/**
	 * Scripts get deps/version from the .asset.php file; bundles without build files are skipped.
	 */
	public function test_register_assets_uses_asset_files_and_skips_missing_bundles(): void {
		$this->write_build_file( 'settings.asset.php', "<?php return [ 'dependencies' => [ 'wp-element' ], 'version' => 'abc123' ];" );
		$this->write_build_file( 'settings.css' );
		$this->write_build_file( 'admin.css' );

		$this->make_assets()->register_assets();

		$script = wp_scripts()->registered[ Assets::SETTINGS_SCRIPT_HANDLE ];
		$this->assertSame( [ 'wp-element' ], $script->deps );
		$this->assertSame( 'abc123', $script->ver );
		$this->assertStringEndsWith( 'build/settings.js', $script->src );
		$this->assertTrue( wp_style_is( Assets::SETTINGS_SCRIPT_HANDLE, 'registered' ) );

		$this->assertTrue( wp_style_is( Assets::ADMIN_STYLES_HANDLE, 'enqueued' ) );
		$this->assertSame( [ 'wp-components' ], wp_styles()->registered[ Assets::ADMIN_STYLES_HANDLE ]->deps );

		$this->assertFalse( wp_script_is( Assets::ONBOARDING_SCRIPT_HANDLE, 'registered' ) );
		$this->assertFalse( wp_script_is( Assets::MULTISITE_SETUP_SCRIPT_HANDLE, 'registered' ) );
	}

	/**
	 * The pattern library bundle is only enqueued, with its localized data, on the pattern editor screen.
	 */
	public function test_enqueue_scripts_loads_pattern_library_on_pattern_screen_only(): void {
		$this->write_build_file( 'patterns-library.asset.php', "<?php return [ 'dependencies' => [], 'version' => '1' ];" );
		$this->write_build_file( 'editor.css' );
		$this->write_build_file( 'templates-library.asset.php', "<?php return [ 'dependencies' => [], 'version' => '1' ];" );
		$assets = $this->make_assets();

		set_current_screen( 'dashboard' );
		$assets->enqueue_scripts();
		$this->assertFalse( wp_script_is( Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, 'registered' ) );

		set_current_screen( Pattern::get_slug() );
		$assets->enqueue_scripts();

		$this->assertTrue( wp_script_is( Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Assets::EDITOR_STYLES_HANDLE, 'enqueued' ) );
		$this->assertStringContainsString( 'var patternSyncData', (string) wp_scripts()->get_data( Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, 'data' ) );
		$this->assertFalse( wp_script_is( Assets::TEMPLATE_LIBRARY_SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * The template library bundle is enqueued with its localized data on the template editor screen.
	 */
	public function test_enqueue_scripts_loads_template_library_on_template_screen(): void {
		$this->write_build_file( 'templates-library.asset.php', "<?php return [ 'dependencies' => [], 'version' => '1' ];" );
		$this->write_build_file( 'template.css' );

		set_current_screen( Template::get_slug() );
		$this->make_assets()->enqueue_scripts();

		$this->assertTrue( wp_script_is( Assets::TEMPLATE_LIBRARY_SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Assets::TEMPLATE_LIBRARY_SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertStringContainsString( 'var TemplateLibraryData', (string) wp_scripts()->get_data( Assets::TEMPLATE_LIBRARY_SCRIPT_HANDLE, 'data' ) );
		$this->assertFalse( wp_script_is( Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * Only the settings and onboarding bundles are deferred, and never twice.
	 */
	public function test_defer_scripts_only_defers_plugin_bundles_once(): void {
		$assets = $this->make_assets();
		$tag    = '<script src="https://example.com/app.js"></script>';

		$deferred = $assets->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE );

		$this->assertSame( '<script defer src="https://example.com/app.js"></script>', $deferred );
		$this->assertSame( $deferred, $assets->defer_scripts( $deferred, Assets::ONBOARDING_SCRIPT_HANDLE ) );
		$this->assertSame( $tag, $assets->defer_scripts( $tag, Assets::PATTERN_LIBRARY_SCRIPT_HANDLE ) );
	}

	/**
	 * Localized data exposes the decrypted API key and is cached across calls.
	 */
	public function test_get_localized_data_exposes_api_key_and_is_cached(): void {
		$data = Assets::get_localized_data();

		$this->assertSame( Settings::get_api_key(), $data['apiKey'] );
		$this->assertSame( esc_url( home_url( '/wp-json' ) ), $data['restUrl'] );
		$this->assertStringContainsString( 'page=onedesign-settings', $data['settingsLink'] );

		Settings::regenerate_api_key();
		$this->assertSame( $data, Assets::get_localized_data() );
	}

	/**
	 * Create an Assets instance that reads build files from the fixture directory.
	 */
	private function make_assets(): Assets {
		$assets = new Assets();
		( new \ReflectionProperty( Assets::class, 'plugin_dir' ) )->setValue( $assets, $this->fixture_dir );

		return $assets;
	}

	/**
	 * Write a fixture file into the build directory.
	 *
	 * @param string $name    File name.
	 * @param string $content File contents.
	 */
	private function write_build_file( string $name, string $content = '' ): void {
		file_put_contents( $this->fixture_dir . 'build/' . $name, $content ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
	}
}
