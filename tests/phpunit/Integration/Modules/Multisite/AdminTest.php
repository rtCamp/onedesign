<?php
/**
 * Multisite admin unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Multisite
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Tests reset WP globals as fixtures.
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Multisite;

use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Multisite\Admin;
use OneDesign\Modules\Multisite\Settings as MU_Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the network governing-site selection modal.
 */
#[CoversClass( Admin::class )]
#[Group( 'ms-required' )]
final class AdminTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->preserve_globals( [ 'wp_scripts' ] );

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$GLOBALS['wp_scripts'] = new \WP_Scripts();
		wp_register_script( Assets::MULTISITE_SETUP_SCRIPT_HANDLE, 'https://example.com/multisite.js', [], '1', true );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * The network plugins screen shows the selection modal until a governing site is chosen.
	 */
	public function test_selection_modal_shown_until_governing_site_selected(): void {
		set_current_screen( 'plugins-network' );
		$admin = new Admin();

		$this->assertStringContainsString( 'onedesign-multisite-selection-modal', $admin->add_admin_body_class( '' ) );

		$admin->enqueue_scripts();
		$this->assertTrue( wp_script_is( Assets::MULTISITE_SETUP_SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertStringContainsString( 'var OneDesignMultiSiteSettings', (string) wp_scripts()->get_data( Assets::MULTISITE_SETUP_SCRIPT_HANDLE, 'data' ) );

		ob_start();
		$admin->render_governing_site_modal();
		$this->assertStringContainsString( 'id="onedesign-multisite-selection-modal"', (string) ob_get_clean() );

		MU_Settings::set_multisite_governing_site_id( 1 );

		$this->assertSame( 'existing', $admin->add_admin_body_class( 'existing' ) );
		ob_start();
		$admin->render_governing_site_modal();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Other screens never show the modal.
	 */
	public function test_selection_modal_hidden_on_other_screens(): void {
		set_current_screen( 'plugins' );
		$admin = new Admin();

		$this->assertSame( '', $admin->add_admin_body_class( '' ) );

		$admin->enqueue_scripts();
		$this->assertFalse( wp_script_is( Assets::MULTISITE_SETUP_SCRIPT_HANDLE, 'enqueued' ) );

		ob_start();
		$admin->render_governing_site_modal();
		$this->assertSame( '', ob_get_clean() );
	}
}
