<?php
/**
 * Multisite settings unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Multisite
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Multisite;

use OneDesign\Modules\Multisite\Settings as MU_Settings;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for keeping the governing site in sync across a network.
 */
#[CoversClass( MU_Settings::class )]
final class SettingsTest extends TestCase {
	/**
	 * On single sites the multisite helpers are inert.
	 */
	#[Group( 'ms-excluded' )]
	public function test_single_site_has_no_governing_site(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site only.' );
		}

		$this->assertNull( MU_Settings::get_multisite_governing_site_id() );
		$this->assertFalse( MU_Settings::set_multisite_governing_site_id( 1 ) );
		$this->assertFalse( MU_Settings::is_governing_site_selected() );
		$this->assertSame( [], MU_Settings::get_all_multisites_info() );
	}

	/**
	 * The governing site ID is stored network-wide.
	 */
	#[Group( 'ms-required' )]
	public function test_governing_site_id_roundtrip(): void {
		$this->skip_unless_multisite();

		$this->assertFalse( MU_Settings::is_governing_site_selected() );

		$this->assertTrue( MU_Settings::set_multisite_governing_site_id( 1 ) );

		$this->assertSame( 1, MU_Settings::get_multisite_governing_site_id() );
		$this->assertTrue( MU_Settings::is_governing_site_selected() );
	}

	/**
	 * All network sites are listed with string IDs, names and URLs.
	 */
	#[Group( 'ms-required' )]
	public function test_get_all_multisites_info_lists_network_sites(): void {
		$this->skip_unless_multisite();

		$blog_id = self::factory()->blog->create( [ 'title' => 'Brand Site' ] );

		$sites = array_column( MU_Settings::get_all_multisites_info(), null, 'id' );

		$this->assertSame( 'Brand Site', $sites[ (string) $blog_id ]['name'] );
		$this->assertSame( get_site_url( $blog_id ), $sites[ (string) $blog_id ]['url'] );
		$this->assertArrayHasKey( '1', $sites );
	}

	/**
	 * A regenerated brand-site key is copied into the governing site's brand list.
	 */
	#[Group( 'ms-required' )]
	public function test_sync_api_key_to_governing_site(): void {
		$this->skip_unless_multisite();

		$brand_id = self::factory()->blog->create();
		MU_Settings::set_multisite_governing_site_id( 1 );
		Settings::set_shared_sites(
			[
				[
					'id'      => (string) $brand_id,
					'name'    => 'Brand',
					'url'     => get_site_url( $brand_id ),
					'api_key' => 'old-key',
				],
			]
		);

		switch_to_blog( $brand_id );
		( new MU_Settings() )->sync_api_key_to_governing_site( 'new-key', $brand_id );
		restore_current_blog();

		$this->assertSame( 'new-key', Settings::get_shared_sites()[ get_site_url( $brand_id ) ]['api_key'] );
	}

	/**
	 * New network sites become brand sites once a governing site exists.
	 */
	#[Group( 'ms-required' )]
	public function test_new_sites_become_brand_sites(): void {
		$this->skip_unless_multisite();

		$settings = new MU_Settings();
		$before   = self::factory()->blog->create();
		$settings->assign_brand_site_on_new_site_creation( get_site( $before ) );
		$this->assertFalse( get_blog_option( $before, Settings::OPTION_SITE_TYPE ), 'No governing site, no assignment' );

		MU_Settings::set_multisite_governing_site_id( 1 );
		$after = self::factory()->blog->create();
		$settings->assign_brand_site_on_new_site_creation( get_site( $after ) );

		$this->assertSame( Settings::SITE_TYPE_CONSUMER, get_blog_option( $after, Settings::OPTION_SITE_TYPE ) );
	}

	/**
	 * Name and icon changes on a brand site are mirrored into the governing site's brand list.
	 */
	#[Group( 'ms-required' )]
	public function test_brand_site_detail_changes_sync_to_governing_site(): void {
		$this->skip_unless_multisite();

		$brand_id = self::factory()->blog->create();
		MU_Settings::set_multisite_governing_site_id( 1 );
		Settings::set_shared_sites(
			[
				[
					'id'   => (string) $brand_id,
					'name' => 'Old Name',
					'url'  => get_site_url( $brand_id ),
				],
			]
		);
		$settings = new MU_Settings();

		switch_to_blog( $brand_id );
		$icon_id = self::factory()->attachment->create_object( 'icon.png', 0, [ 'post_mime_type' => 'image/png' ] );
		$settings->update_site_details_in_governing_site_table( 'blogname', 'Old Name', 'New <b>Name</b>' );
		$settings->update_site_details_in_governing_site_table( 'site_icon', 0, $icon_id );
		$icon_url = wp_get_attachment_url( $icon_id );
		$settings->update_site_details_in_governing_site_table( 'blogdescription', '', 'Ignored' );
		restore_current_blog();

		$site = Settings::get_shared_sites()[ get_site_url( $brand_id ) ];
		$this->assertSame( 'New Name', $site['name'] );
		$this->assertSame( $icon_url, $site['logo'] );
		$this->assertSame( $icon_id, $site['logo_id'] );
	}

	/**
	 * Skip the test on single-site installs.
	 */
	private function skip_unless_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
	}
}
