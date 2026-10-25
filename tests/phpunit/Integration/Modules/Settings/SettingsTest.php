<?php
/**
 * Settings unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Settings
 */

declare(strict_types = 1);

namespace OneDesign\Tests\Integration\Modules\Settings;

use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Class SettingsTest */
#[CoversClass( Settings::class )]
final class SettingsTest extends TestCase {
	/** @var \OneDesign\Modules\Settings\Settings */
	private Settings $settings;

	/** {@inheritDoc} */
	protected function setUp(): void {
		parent::setUp();

		$this->settings = new Settings();
	}

	/** {@inheritDoc} */
	protected function tearDown(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_CONSUMER_API_KEY );
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

		parent::tearDown();
	}

	/** Ensures the class can be instantiated and hook methods can be called without error. */
	public function test_class_instantiation(): void {
		$this->settings->register_hooks();
		$this->settings->register_settings();

		$this->assertTrue( true );
	}

	/** Ensures register_settings registers the site type setting. */
	public function test_register_settings_registers_site_type(): void {
		$this->settings->register_settings();

		$this->assertSettingRegistered( Settings::OPTION_SITE_TYPE );
	}

	/** Ensures register_settings registers consumer options. */
	public function test_register_settings_registers_consumer_options(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$this->settings->register_settings();

		$this->assertSettingRegistered( Settings::OPTION_CONSUMER_API_KEY );
		$this->assertSettingRegistered( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
	}

	/** Ensures register_settings registers governing options. */
	public function test_register_settings_registers_governing_options(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$this->settings->register_settings();

		$this->assertSettingRegistered( Settings::OPTION_GOVERNING_SHARED_SITES );
	}

	/** The brand sites hold their keys, so they aren't exposed through the core settings route. */
	public function test_shared_sites_are_not_in_the_rest_settings(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$this->settings->register_settings();

		$this->assertFalse( get_registered_settings()[ Settings::OPTION_GOVERNING_SHARED_SITES ]['show_in_rest'] );
	}

	/** Ensures get_site_type returns null when not set. */
	public function test_get_site_type_returns_null_when_not_set(): void {
		delete_option( Settings::OPTION_SITE_TYPE );

		$this->assertNull( Settings::get_site_type() );
	}

	/** Ensures get_site_type returns a value when set. */
	public function test_get_site_type_returns_value_when_set(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$this->assertSame( Settings::SITE_TYPE_CONSUMER, Settings::get_site_type() );
	}

	/** Ensures governing site detection works. */
	public function test_is_governing_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$this->assertTrue( Settings::is_governing_site() );
		$this->assertFalse( Settings::is_consumer_site() );
	}

	/** Ensures consumer site detection works. */
	public function test_is_consumer_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$this->assertTrue( Settings::is_consumer_site() );
		$this->assertFalse( Settings::is_governing_site() );
	}

	/** Ensures sanitize_shared_sites handles valid data. */
	public function test_sanitize_shared_sites_with_valid_data(): void {
		$sanitized = Settings::sanitize_shared_sites(
			[
				[
					'id'      => ' site-id ',
					'name'    => ' Demo Site ',
					'url'     => 'https://example.com/path/',
					'logo'    => 'https://example.com/logo.png',
					'logo_id' => '42',
					'api_key' => ' secret-key ',
				],
			]
		);

		$this->assertSame(
			[
				[
					'id'      => 'site-id',
					'name'    => 'Demo Site',
					'url'     => 'https://example.com/path',
					'logo'    => 'https://example.com/logo.png',
					'logo_id' => 42,
					'api_key' => 'secret-key',
				],
			],
			$sanitized
		);
	}

	/** Ensures sanitize_shared_sites returns an empty array for non-array input. */
	public function test_sanitize_shared_sites_returns_empty_for_non_array(): void {
		$this->assertSame( [], Settings::sanitize_shared_sites( 'not-an-array' ) );
	}

	/** Ensures sanitize_shared_sites generates a UUID for a missing ID. */
	public function test_sanitize_shared_sites_generates_uuid_for_missing_id(): void {
		$sanitized = Settings::sanitize_shared_sites(
			[
				[
					'name' => 'Demo Site',
					'url'  => 'https://example.com',
				],
			]
		);

		$this->assertCount( 1, $sanitized );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/i', $sanitized[0]['id'] );
	}

	/** Ensures sanitize_shared_sites skips entries without a name or URL. */
	public function test_sanitize_shared_sites_skips_sites_without_name_or_url(): void {
		$sanitized = Settings::sanitize_shared_sites(
			[
				[
					'name' => 'Missing URL',
				],
			]
		);

		$this->assertSame( [], $sanitized );
	}

	/** Ensures get_shared_sites returns an empty array when not set. */
	public function test_get_shared_sites_returns_empty_when_not_set(): void {
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/** Ensures shared sites round-trip through storage. */
	public function test_set_and_get_shared_sites_roundtrip(): void {
		$sites = [
			[
				'id'      => 'brand-1',
				'name'    => 'Brand One',
				'url'     => 'https://brand-one.example',
				'logo'    => 'https://brand-one.example/logo.png',
				'logo_id' => 11,
				'api_key' => 'brand-one-key',
			],
		];

		$this->assertTrue( Settings::set_shared_sites( $sites ) );

		$stored_sites = get_option( Settings::OPTION_GOVERNING_SHARED_SITES, [] );
		$this->assertNotSame( 'brand-one-key', $stored_sites[0]['api_key'] );

		$this->assertSame(
			[
				'https://brand-one.example' => [
					'api_key' => 'brand-one-key',
					'id'      => 'brand-1',
					'logo'    => 'https://brand-one.example/logo.png',
					'logo_id' => 11,
					'name'    => 'Brand One',
					'url'     => 'https://brand-one.example',
				],
			],
			Settings::get_shared_sites()
		);
	}

	/** Ensures parent site URL can be stored and retrieved. */
	public function test_set_parent_site_url_and_get(): void {
		$this->assertTrue( Settings::set_parent_site_url( 'https://governing.example/' ) );
		$this->assertSame( 'https://governing.example', Settings::get_parent_site_url() );
	}

	/** Ensures get_api_key generates a key if none is set. */
	public function test_get_api_key_generates_if_not_set(): void {
		$api_key = Settings::get_api_key();

		$this->assertNotSame( '', $api_key );
		$this->assertNotSame( $api_key, get_option( Settings::OPTION_CONSUMER_API_KEY, '' ) );
		$this->assertSame( $api_key, Settings::get_api_key() );
	}

	/** Ensures regenerate_api_key returns a new key. */
	public function test_regenerate_api_key_returns_new_key(): void {
		$initial_api_key = Settings::get_api_key();
		$new_api_key     = Settings::regenerate_api_key();

		$this->assertNotSame( '', $new_api_key );
		$this->assertNotSame( $initial_api_key, $new_api_key );
		$this->assertSame( $new_api_key, Settings::get_api_key() );
	}

	/**
	 * Ensures a setting is registered.
	 *
	 * @param string $setting_name Setting name.
	 */
	private function assertSettingRegistered( string $setting_name ): void {
		$registered_settings = get_registered_settings();

		$this->assertArrayHasKey( $setting_name, $registered_settings );

		global $new_allowed_options;

		$this->assertContains( $setting_name, $new_allowed_options[ Settings::SETTING_GROUP ] ?? [] );
	}
}
