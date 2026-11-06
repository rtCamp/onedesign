<?php
/**
 * Asset registration unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Core
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Core;

use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Settings\Settings;
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

	/**
	 * Ensures localized data exposes the expected keys.
	 */
	public function test_get_localized_data_returns_expected_keys(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING, false );

		$data = Assets::get_localized_data();

		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'api_key', $data );
		$this->assertArrayHasKey( 'setupUrl', $data );
		$this->assertArrayHasKey( 'sharedSites', $data );
		$this->assertArrayHasKey( 'siteType', $data );
	}

	/**
	 * Keeps the brand sites' API keys out of the page, and doesn't generate a key on governing sites.
	 */
	public function test_get_localized_data_omits_secrets(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING, false );
		delete_option( Settings::OPTION_CONSUMER_API_KEY );
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand',
					'url'     => 'https://brand.example.com',
					'api_key' => 'brand-secret',
				],
			]
		);
		$this->reset_localized_data();

		$data = Assets::get_localized_data();

		$this->assertSame( '', $data['api_key'], 'Governing sites have no consumer key.' );
		$this->assertFalse( get_option( Settings::OPTION_CONSUMER_API_KEY ), 'No consumer key is generated on governing sites.' );
		$this->assertCount( 1, $data['sharedSites'] );
		$this->assertArrayNotHasKey( 'api_key', $data['sharedSites'][0] );
		$this->assertStringNotContainsString( 'brand-secret', (string) wp_json_encode( $data ) );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );
		$this->reset_localized_data();

		$this->assertNotSame( '', Assets::get_localized_data()['api_key'] );

		$this->reset_localized_data();
	}

	/**
	 * Clears the static localized data cache.
	 */
	private function reset_localized_data(): void {
		$property = new \ReflectionProperty( Assets::class, 'localized_data' );
		$property->setValue( null, [] );
	}
}
