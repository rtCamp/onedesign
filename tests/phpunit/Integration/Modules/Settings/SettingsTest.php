<?php
/**
 * Settings unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Settings
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Settings;

use OneDesign\Encryptor;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the plugin settings.
 */
#[CoversClass( Settings::class )]
final class SettingsTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		$this->unregister_settings();

		parent::tearDown();
	}

	/**
	 * Brand sites get consumer settings; governing sites get the shared-sites setting.
	 */
	public function test_register_settings_registers_options_for_site_type(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		( new Settings() )->register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( Settings::OPTION_SITE_TYPE, $registered );
		$this->assertArrayHasKey( Settings::OPTION_CONSUMER_API_KEY, $registered );
		$this->assertArrayHasKey( Settings::OPTION_CONSUMER_PARENT_SITE_URL, $registered );
		$this->assertArrayNotHasKey( Settings::OPTION_GOVERNING_SHARED_SITES, $registered );

		$this->unregister_settings();

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		( new Settings() )->register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( Settings::OPTION_GOVERNING_SHARED_SITES, $registered );
		$this->assertArrayNotHasKey( Settings::OPTION_CONSUMER_API_KEY, $registered );
	}

	/**
	 * Registered sanitizers reject unknown site types and normalize the parent URL.
	 */
	public function test_registered_sanitizers_apply_on_update(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		( new Settings() )->register_settings();

		update_option( Settings::OPTION_SITE_TYPE, 'not-a-site-type' );
		$this->assertSame( '', get_option( Settings::OPTION_SITE_TYPE ) );

		update_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL, 'https://governing.example/' );
		$this->assertSame( 'https://governing.example', Settings::get_parent_site_url() );
	}

	/**
	 * Shared sites are trimmed, URL-normalized, given an ID, and dropped when name/url is missing.
	 */
	public function test_sanitize_shared_sites(): void {
		$sanitized = Settings::sanitize_shared_sites(
			[
				[
					'id'      => ' site-1 ',
					'name'    => ' Brand One ',
					'url'     => 'https://brand-one.example/',
					'logo'    => 'https://brand-one.example/logo.png',
					'logo_id' => '42',
					'api_key' => ' key ',
				],
				[
					'name' => 'No ID',
					'url'  => 'https://brand-two.example',
				],
				[ 'name' => 'Missing URL' ],
				'not-an-array',
			]
		);

		$this->assertCount( 2, $sanitized );
		$this->assertSame(
			[
				'id'      => 'site-1',
				'name'    => 'Brand One',
				'url'     => 'https://brand-one.example',
				'logo'    => 'https://brand-one.example/logo.png',
				'logo_id' => 42,
				'api_key' => 'key',
			],
			$sanitized[0]
		);
		$this->assertTrue( wp_is_uuid( $sanitized[1]['id'] ) );
		$this->assertSame( [], Settings::sanitize_shared_sites( 'not-an-array' ) );
	}

	/**
	 * Stored shared sites come back keyed by URL with defaults filled in; sites without a URL are skipped.
	 */
	public function test_set_and_get_shared_sites(): void {
		$this->assertSame( [], Settings::get_shared_sites() );

		Settings::set_shared_sites(
			[
				'ignored-key' => [
					'name' => 'Brand One',
					'url'  => 'https://brand-one.example',
				],
				[ 'name' => 'No URL' ],
			]
		);

		$this->assertSame( [ 0, 1 ], array_keys( get_option( Settings::OPTION_GOVERNING_SHARED_SITES ) ), 'Stored list should be re-indexed' );
		$this->assertSame(
			[
				'https://brand-one.example' => [
					'api_key' => '',
					'id'      => '',
					'logo'    => '',
					'logo_id' => 0,
					'name'    => 'Brand One',
					'url'     => 'https://brand-one.example',
				],
			],
			Settings::get_shared_sites()
		);
	}

	/**
	 * Changing site type generates a key through the registered hook only for brand sites.
	 */
	public function test_site_type_changes_generate_keys_only_for_brand_sites(): void {
		( new Settings() )->register_hooks();
		$this->assertNull( Settings::get_site_type() );
		$this->assertFalse( Settings::is_governing_site() );
		$this->assertFalse( Settings::is_consumer_site() );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		$this->assertTrue( Settings::is_governing_site() );
		$this->assertFalse( get_option( Settings::OPTION_CONSUMER_API_KEY ) );
		$this->assertFalse( Settings::is_consumer_site() );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		$this->assertTrue( Settings::is_consumer_site() );
		$this->assertSame( Settings::SITE_TYPE_CONSUMER, Settings::get_site_type() );
		$this->assertNotEmpty( get_option( Settings::OPTION_CONSUMER_API_KEY ) );
	}

	/**
	 * API keys persist encrypted, remain stable on reads, and rotate explicitly.
	 */
	public function test_get_api_key_generates_and_persists_encrypted_key(): void {
		$api_key = Settings::get_api_key();
		$stored  = get_option( Settings::OPTION_CONSUMER_API_KEY );

		$this->assertSame( 128, strlen( $api_key ) );
		$this->assertNotSame( $api_key, $stored );
		$this->assertSame( $api_key, Encryptor::decrypt( $stored ) );
		$this->assertSame( $api_key, Settings::get_api_key() );

		$new_key = Settings::regenerate_api_key();
		$this->assertNotSame( $api_key, $new_key );
		$this->assertSame( $new_key, Settings::get_api_key() );
		$this->assertSame( $new_key, Encryptor::decrypt( get_option( Settings::OPTION_CONSUMER_API_KEY ) ) );
	}

	/**
	 * The parent site URL is stored untrailingslashed.
	 */
	public function test_set_parent_site_url_normalizes_url(): void {
		$this->assertNull( Settings::get_parent_site_url() );

		Settings::set_parent_site_url( 'https://governing.example/' );

		$this->assertSame( 'https://governing.example', Settings::get_parent_site_url() );
	}

	/**
	 * Unregister every plugin setting.
	 */
	private function unregister_settings(): void {
		foreach ( [ Settings::OPTION_SITE_TYPE, Settings::OPTION_CONSUMER_API_KEY, Settings::OPTION_CONSUMER_PARENT_SITE_URL, Settings::OPTION_GOVERNING_SHARED_SITES ] as $option ) {
			unregister_setting( Settings::SETTING_GROUP, $option );
		}
	}
}
