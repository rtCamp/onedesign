<?php
/**
 * Asset registration unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Core
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Core;

use OneDesign\Modules\Core\Assets;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for asset registration and localization.
 */
#[CoversClass( \OneDesign\Modules\Core\Assets::class )]
final class AssetsTest extends TestCase {
	/**
	 * Tests no errors on class instantiation.
	 */
	public function test_assets_class_instantiation(): void {
		$assets = new Assets();

		$assets->register_hooks();
		$assets->register_assets();

		$this->assertTrue( true );
	}

	/**
	 * Registering a script again still gives it the dependencies from its asset file.
	 */
	public function test_registering_a_script_again_keeps_its_dependencies(): void {
		$assets = new Assets();

		$assets->register_assets();
		wp_deregister_script( Assets::SETTINGS_SCRIPT_HANDLE );
		$assets->register_assets();

		$asset = require ONEDESIGN_DIR . 'build/settings.asset.php';

		$this->assertNotEmpty( $asset['dependencies'] );
		$this->assertSame( $asset['dependencies'], wp_scripts()->registered[ Assets::SETTINGS_SCRIPT_HANDLE ]->deps );
	}

	/**
	 * Ensures plugin scripts receive a defer attribute.
	 */
	public function test_defer_scripts_adds_defer_for_plugin_handles(): void {
		$assets = new Assets();
		$tag    = '<script src="https://example.com/script.js"></script>';

		$actual = $assets->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE );

		$this->assertSame( '<script defer src="https://example.com/script.js"></script>', $actual );
	}

	/**
	 * Ensures unrelated script handles are unchanged.
	 */
	public function test_defer_scripts_does_not_modify_other_handles(): void {
		$assets = new Assets();
		$tag    = '<script src="https://example.com/script.js"></script>';

		$this->assertSame( $tag, $assets->defer_scripts( $tag, 'other-handle' ) );
	}

	/**
	 * Ensures defer is not duplicated on existing script tags.
	 */
	public function test_defer_scripts_does_not_duplicate_defer_attribute(): void {
		$assets = new Assets();
		$tag    = '<script defer src="https://example.com/script.js"></script>';

		$this->assertSame( $tag, $assets->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE ) );
	}
}
