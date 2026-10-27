<?php
/**
 * Network admin screen tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Multisite
 */

declare(strict_types = 1);

namespace OneDesign\Tests\Integration\Modules\Multisite;

use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Multisite\Admin;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for Network Admin > Settings > OneDesign, where network admins make governing sites and add brand sites to them.
 */
#[CoversClass( Admin::class )]
final class AdminTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->skipWithoutMultisite();

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );
	}

	/**
	 * The screen is there for network admins, and not for site admins.
	 */
	public function test_the_network_screen_is_for_network_admins(): void {
		( new Admin() )->add_submenu();
		$hook = get_plugin_page_hook( Admin::SCREEN_ID, 'settings.php' );
		$this->assertNotNull( $hook );

		remove_all_actions( $hook );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		( new Admin() )->add_submenu();
		$this->assertNull( get_plugin_page_hook( Admin::SCREEN_ID, 'settings.php' ) );
	}

	/**
	 * Where OneDesign is active only on a site, the network admin gets no screen or notice, since the routes they use aren't there.
	 */
	public function test_there_is_no_screen_or_notice_unless_network_activated(): void {
		update_site_option( 'active_sitewide_plugins', [] );
		remove_all_actions( 'network_admin_menu' );
		remove_all_actions( 'network_admin_notices' );
		set_current_screen( 'plugins-network' );

		( new Admin() )->register_hooks();
		do_action( 'network_admin_menu' );

		ob_start();
		do_action( 'network_admin_notices' );
		$notices = (string) ob_get_clean();

		$this->assertNull( get_plugin_page_hook( Admin::SCREEN_ID, 'settings.php' ) );
		$this->assertStringNotContainsString( 'needs to be set up', $notices );
	}

	/**
	 * The screen lists every governing site and every site that can be added as a brand site, the current one included.
	 */
	public function test_the_network_screen_lists_the_network_sites(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		// Core stores the name HTML-escaped.
		$new_site_id = self::factory()->blog->create( [ 'title' => 'Sales & Marketing' ] );

		$unconnected_brand_id = self::factory()->blog->create();
		update_blog_option( $unconnected_brand_id, Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$connected_brand_id = self::factory()->blog->create();
		switch_to_blog( $connected_brand_id );
		$this->set_up_brand_site();
		restore_current_blog();

		( new Assets() )->register_assets();
		wp_scripts()->add_data( Assets::NETWORK_SETTINGS_SCRIPT_HANDLE, 'data', '' );
		( new Admin() )->enqueue_scripts( Admin::SCREEN_HOOK );

		preg_match( '/var OneDesignSettings = (.*);/', (string) wp_scripts()->get_data( Assets::NETWORK_SETTINGS_SCRIPT_HANDLE, 'data' ), $matches );
		$multisites = json_decode( $matches[1], true )['multisites'];

		$this->assertSame(
			[
				[
					'id'       => (string) get_current_blog_id(),
					'siteType' => Settings::SITE_TYPE_GOVERNING,
				],
				[
					'id'       => (string) $new_site_id,
					'siteType' => '',
				],
				[
					'id'       => (string) $unconnected_brand_id,
					'siteType' => Settings::SITE_TYPE_CONSUMER,
				],
			],
			array_map( static fn ( array $site ): array => array_intersect_key( $site, array_flip( [ 'id', 'siteType' ] ) ), $multisites )
		);
		$this->assertSame( 'Sales & Marketing', $multisites[1]['name'] );
		$this->assertSame( trailingslashit( get_blog_option( $new_site_id, 'home' ) ), $multisites[1]['url'] );
	}

	/**
	 * While no site on the network has a type, the setup notice links network admins to the network screen.
	 *
	 * @param string $screen Screen to render the notice on.
	 */
	#[DataProvider( 'setup_notice_screen_provider' )]
	public function test_the_setup_notice_links_to_the_network_screen( string $screen ): void {
		set_current_screen( $screen );

		$output = $this->get_notices();

		$this->assertStringContainsString( 'OneDesign needs to be set up', $output );
		$this->assertStringContainsString( esc_url( network_admin_url( 'settings.php?page=' . Admin::SCREEN_ID ) ), $output );
	}

	/**
	 * Provides network screens that show the setup notice.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function setup_notice_screen_provider(): array {
		return [
			'plugins'   => [ 'plugins-network' ],
			'dashboard' => [ 'dashboard-network' ],
		];
	}

	/**
	 * The setup notice is skipped where it doesn't apply.
	 */
	public function test_the_setup_notice_is_skipped_when_not_applicable(): void {
		// Unrelated screen.
		set_current_screen( 'sites-network' );
		$this->assertStringNotContainsString( 'needs to be set up', $this->get_notices() );

		// No permission.
		set_current_screen( 'plugins-network' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertStringNotContainsString( 'needs to be set up', $this->get_notices() );

		// A site on the network has a type.
		grant_super_admin( get_current_user_id() );
		update_blog_option( self::factory()->blog->create(), Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		$this->assertStringNotContainsString( 'needs to be set up', $this->get_notices() );
	}

	/**
	 * Captures the network admin notices.
	 */
	private function get_notices(): string {
		ob_start();
		( new Admin() )->render_notices();

		return (string) ob_get_clean();
	}
}
