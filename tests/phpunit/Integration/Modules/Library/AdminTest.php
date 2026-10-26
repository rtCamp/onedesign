<?php
/**
 * Tests for the Pattern Library screen.
 *
 * @package OneDesign\Tests\Integration\Modules\Library
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Library;

use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Library\Admin;
use OneDesign\Modules\Settings\Admin as Settings_Admin;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class - AdminTest
 */
#[CoversClass( Admin::class )]
final class AdminTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		unset( $GLOBALS['submenu'][ Settings_Admin::MENU_SLUG ] );

		foreach ( [ Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, 'wp-editor' ] as $handle ) {
			wp_dequeue_script( $handle );
		}
		wp_dequeue_style( Assets::LIBRARY_STYLES_HANDLE );

		if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'lab/block' ) ) {
			unregister_block_type( 'lab/block' );
		}
		wp_deregister_script( 'lab-block-editor' );
		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	/**
	 * Brand sites don't get the Pattern Library.
	 */
	public function test_brand_site_has_no_pattern_library(): void {
		$this->set_up_brand_site();

		( new Admin() )->add_submenu();

		$this->assertSame( [], $this->get_submenu_slugs() );
	}

	/**
	 * The Pattern Library screen has the element its app renders into.
	 */
	public function test_pattern_library_screen_has_the_element_its_app_renders_into(): void {
		ob_start();
		( new Admin() )->render_pattern_library();
		$pattern_library = (string) ob_get_clean();

		$this->assertStringContainsString( '<div id="onedesign-pattern-library"></div>', $pattern_library );
	}

	/**
	 * The Pattern Library's script and the library styles load on its screen and nowhere else.
	 *
	 * @param string $hook     The admin page hook.
	 * @param bool   $expected Whether they load there.
	 */
	#[DataProvider( 'admin_screens' )]
	public function test_pattern_library_loads_only_on_its_own_screen( string $hook, bool $expected ): void {
		( new Assets() )->register_assets();
		set_current_screen( $hook );

		( new Admin() )->enqueue_scripts( $hook );

		$this->assertSame( $expected, wp_script_is( Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertSame( $expected, wp_style_is( Assets::LIBRARY_STYLES_HANDLE, 'enqueued' ) );
	}

	/**
	 * A block another plugin registers with an editor script loads on the Pattern Library, with its server definition, so its previews render it.
	 */
	public function test_pattern_library_loads_blocks_other_plugins_register(): void {
		wp_register_script( 'lab-block-editor', 'https://example.org/lab-block.js', [ 'wp-blocks' ], '1', true );
		register_block_type(
			'lab/block',
			[
				'title'         => 'Lab Block',
				'editor_script' => 'lab-block-editor',
			]
		);
		( new Assets() )->register_assets();
		set_current_screen( Admin::PATTERN_LIBRARY_HOOK );
		$admin = new Admin();

		$admin->load_block_editor();
		$admin->enqueue_scripts( Admin::PATTERN_LIBRARY_HOOK );

		$this->assertTrue( wp_script_is( 'lab-block-editor', 'enqueued' ) );
		$this->assertStringContainsString( '"lab/block":{', implode( '', (array) wp_scripts()->get_data( 'wp-blocks', 'after' ) ) );
	}

	/**
	 * The admin screens, and whether the Pattern Library loads on each.
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function admin_screens(): array {
		return [
			'Pattern Library' => [ Admin::PATTERN_LIBRARY_HOOK, true ],
			'Settings'        => [ Settings_Admin::SETTINGS_HOOK, false ],
			'menu page'       => [ 'toplevel_page_' . Settings_Admin::MENU_SLUG, false ],
			'post editor'     => [ 'post.php', false ],
			'dashboard'       => [ 'index.php', false ],
		];
	}

	/**
	 * Gets the slugs of the plugin's submenu items.
	 *
	 * @return string[]
	 */
	private function get_submenu_slugs(): array {
		$slugs = array_column( (array) ( $GLOBALS['submenu'][ Settings_Admin::MENU_SLUG ] ?? [] ), 2 );
		sort( $slugs );

		return $slugs;
	}
}
