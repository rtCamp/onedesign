<?php
/**
 * Tests for the library screens.
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

		foreach ( [ Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, Assets::TEMPLATE_LIBRARY_SCRIPT_HANDLE, 'wp-editor' ] as $handle ) {
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
	 * A governing site gets the libraries before it connects a brand site, so they're there as soon as the first one is saved.
	 */
	public function test_governing_site_has_the_libraries_before_any_brand_site(): void {
		$this->set_up_governing_site( [] );

		( new Admin() )->add_submenu();

		$this->assertSame( [ Admin::PATTERN_LIBRARY_SCREEN_ID, Admin::TEMPLATE_LIBRARY_SCREEN_ID ], $this->get_submenu_slugs() );
	}

	/**
	 * Brand sites don't get the libraries.
	 */
	public function test_brand_site_has_no_libraries(): void {
		$this->set_up_brand_site();

		( new Admin() )->add_submenu();

		$this->assertSame( [], $this->get_submenu_slugs() );
	}

	/**
	 * Each library renders into its own element on its own screen.
	 */
	public function test_each_library_screen_has_the_element_its_app_renders_into(): void {
		ob_start();
		( new Admin() )->render_pattern_library();
		$pattern_library = (string) ob_get_clean();

		ob_start();
		( new Admin() )->render_template_library();
		$template_library = (string) ob_get_clean();

		$this->assertStringContainsString( '<div id="onedesign-pattern-library"></div>', $pattern_library );
		$this->assertStringContainsString( '<div id="onedesign-template-library"></div>', $template_library );
	}

	/**
	 * Each library's script and the library styles load on that library's screen and nowhere else.
	 *
	 * @param string  $hook   The admin page hook.
	 * @param ?string $handle The script that loads there, if any.
	 */
	#[DataProvider( 'admin_screens' )]
	public function test_each_library_loads_only_on_its_own_screen( string $hook, ?string $handle ): void {
		( new Assets() )->register_assets();
		set_current_screen( $hook );

		( new Admin() )->enqueue_scripts( $hook );

		foreach ( [ Assets::PATTERN_LIBRARY_SCRIPT_HANDLE, Assets::TEMPLATE_LIBRARY_SCRIPT_HANDLE ] as $library ) {
			$this->assertSame( $library === $handle, wp_script_is( $library, 'enqueued' ), $library );
		}
		$this->assertSame( null !== $handle, wp_style_is( Assets::LIBRARY_STYLES_HANDLE, 'enqueued' ) );
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
	 * The admin screens, and which library script loads on each.
	 *
	 * @return array<string,array{string,?string}>
	 */
	public static function admin_screens(): array {
		return [
			'Pattern Library'  => [ Admin::PATTERN_LIBRARY_HOOK, Assets::PATTERN_LIBRARY_SCRIPT_HANDLE ],
			'Template Library' => [ Admin::TEMPLATE_LIBRARY_HOOK, Assets::TEMPLATE_LIBRARY_SCRIPT_HANDLE ],
			'Settings'         => [ Settings_Admin::SETTINGS_HOOK, null ],
			'menu page'        => [ 'toplevel_page_' . Settings_Admin::MENU_SLUG, null ],
			'post editor'      => [ 'post.php', null ],
			'dashboard'        => [ 'index.php', null ],
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
