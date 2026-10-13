<?php
/**
 * CPT_Restriction unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Post_Types
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Tests reset WP globals as fixtures.
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Post_Types;

use OneDesign\Modules\Post_Types\CPT_Restriction;
use OneDesign\Modules\Post_Types\Pattern;
use OneDesign\Modules\Post_Types\Template;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the library post type restrictions.
 */
#[CoversClass( CPT_Restriction::class )]
final class CPT_RestrictionTest extends TestCase {
	/**
	 * Restriction instance under test.
	 */
	private CPT_Restriction $restriction;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->preserve_globals( [ 'submenu', 'wp_post_types' ] );

		$this->restriction = new CPT_Restriction();
		add_filter( 'register_post_type_args', [ $this->restriction, 'restrict_cpt' ], 5, 2 );
		add_filter( 'register_post_type_args', [ $this->restriction, 'modify_pattern_library_labels' ], 10, 2 );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		unregister_post_type( Pattern::get_slug() );
		unregister_post_type( Template::get_slug() );
		unset( $GLOBALS['current_screen'], $GLOBALS['submenu'] );

		parent::tearDown();
	}

	/**
	 * On brand sites the library post types are locked down and then unregistered.
	 */
	public function test_brand_site_locks_down_and_unregisters_library_post_types(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		$this->register_post_types();

		$pattern = get_post_type_object( Pattern::get_slug() );
		$this->assertFalse( $pattern->show_ui );
		$this->assertFalse( $pattern->show_in_rest );
		$this->assertSame( 'do_not_allow', $pattern->cap->edit_posts );

		$this->assertSame( [ 'public' => true ], $this->restriction->restrict_cpt( [ 'public' => true ], 'post' ), 'Other post types are untouched' );

		$this->restriction->unregister_cpt();

		$this->assertFalse( post_type_exists( Pattern::get_slug() ) );
		$this->assertFalse( post_type_exists( Template::get_slug() ) );
	}

	/**
	 * On governing sites the library post types stay editable but are hidden from menus.
	 */
	public function test_governing_site_keeps_library_post_types_editable(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		$this->register_post_types();
		$this->restriction->unregister_cpt();

		$template = get_post_type_object( Template::get_slug() );
		$this->assertInstanceOf( \WP_Post_Type::class, $template );
		$this->assertTrue( $template->show_ui );
		$this->assertTrue( $template->show_in_rest );
		$this->assertFalse( $template->show_in_menu );
		$this->assertTrue( $template->exclude_from_search );
	}

	/**
	 * Opening "Add New" redirects to the single existing library post.
	 *
	 * @param string $method    Restriction method to call.
	 * @param string $post_type Library post type slug.
	 *
	 * @dataProvider limit_posts_provider
	 */
	#[DataProvider( 'limit_posts_provider' )]
	public function test_add_new_screen_redirects_to_existing_post( string $method, string $post_type ): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		$this->register_post_types();
		$this->set_add_new_screen( $post_type );

		// No existing post: the add screen is allowed.
		$this->restriction->$method();

		$post_id = self::factory()->post->create(
			[
				'post_type'   => $post_type,
				'post_status' => 'draft',
			]
		);
		$this->intercept_redirects();
		$this->expectExceptionMessage( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );

		$this->restriction->$method();
	}

	/**
	 * Once a pattern library post exists, "Add New" labels and the menu link point to editing it.
	 */
	public function test_pattern_library_add_new_becomes_edit_once_post_exists(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		$this->register_post_types();
		$this->assertSame( 'Add New', get_post_type_object( Pattern::get_slug() )->labels->add_new );

		$post_id = self::factory()->post->create( [ 'post_type' => Pattern::get_slug() ] );
		$this->register_post_types();
		$this->assertSame( 'Edit Pattern Library', get_post_type_object( Pattern::get_slug() )->labels->add_new );

		$parent             = 'edit.php?post_type=' . Pattern::get_slug();
		$GLOBALS['submenu'] = [
			$parent => [
				5  => [ 'All', 'edit_posts', $parent ],
				10 => [ 'Add New', 'edit_posts', 'post-new.php?post_type=' . Pattern::get_slug() ],
			],
		];

		$this->restriction->modify_pattern_library_admin_menu();

		$this->assertSame( [ 'Edit Pattern Library', 'edit_posts', 'post.php?post=' . $post_id . '&action=edit' ], $GLOBALS['submenu'][ $parent ][10] );
		$this->assertSame( $parent, $GLOBALS['submenu'][ $parent ][5][2] );
	}

	/**
	 * New pattern library posts get a default title and instructional content.
	 */
	public function test_new_pattern_library_post_gets_default_title_and_content(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		$this->register_post_types();
		$pattern_post = self::factory()->post->create_and_get( [ 'post_type' => Pattern::get_slug() ] );
		$other_post   = self::factory()->post->create_and_get();

		$this->assertStringContainsString( 'Patterns Selection', $this->restriction->add_default_content_to_editor( '', $pattern_post ) );
		$this->assertSame( 'Existing', $this->restriction->add_default_content_to_editor( 'Existing', $pattern_post ) );
		$this->assertSame( '', $this->restriction->add_default_content_to_editor( '', $other_post ) );

		$this->set_add_new_screen( Pattern::get_slug() );
		$this->assertSame( 'Pattern Library', $this->restriction->add_default_title_to_editor( '' ) );

		$this->set_add_new_screen( 'post' );
		$this->assertSame( 'Draft', $this->restriction->add_default_title_to_editor( 'Draft' ) );
	}

	/**
	 * Provides the "Add New" limiters and their post types.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function limit_posts_provider(): array {
		return [
			'pattern library'  => [ 'limit_pattern_library_posts', Pattern::get_slug() ],
			'template library' => [ 'limit_template_posts', Template::get_slug() ],
		];
	}

	/**
	 * Register both library post types through the restriction filters.
	 */
	private function register_post_types(): void {
		( new Pattern() )->register_post_type();
		( new Template() )->register_post_type();
	}

	/**
	 * Set the current screen to the "Add New" screen of a post type.
	 *
	 * @param string $post_type Post type slug.
	 */
	private function set_add_new_screen( string $post_type ): void {
		$screen         = \WP_Screen::get( $post_type );
		$screen->action = 'add';
		$screen->set_current_screen();
	}
}
