<?php
/**
 * Post types admin unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Post_Types
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Post_Types;

use OneDesign\Modules\Post_Types\Admin;
use OneDesign\Modules\Post_Types\Constants;
use OneDesign\Modules\Post_Types\Pattern;
use OneDesign\Modules\Post_Types\Template;
use OneDesign\Modules\Settings\Admin as SettingsAdmin;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the library admin screens and brand-site registration of shared items.
 */
#[CoversClass( Admin::class )]
final class AdminTest extends TestCase {
	private const TEMPLATE_NAME = 'governing-brand//single-shared';
	private const PATTERN_NAME  = 'governing-brand-hero';

	/**
	 * Admin instance under test.
	 */
	private Admin $admin;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->preserve_globals( [ 'submenu', '_wp_theme_features', 'wp_post_types' ] );

		$this->admin = new Admin();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		if ( \WP_Block_Templates_Registry::get_instance()->is_registered( self::TEMPLATE_NAME ) ) {
			unregister_block_template( self::TEMPLATE_NAME );
		}
		foreach ( [ self::PATTERN_NAME, 'brand/cta' ] as $pattern ) {
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern ) ) {
				unregister_block_pattern( $pattern );
			}
		}
		unregister_post_type( Pattern::get_slug() );
		unregister_post_type( Template::get_slug() );
		unset( $GLOBALS['current_screen'], $GLOBALS['submenu'][ SettingsAdmin::MENU_SLUG ], $_GET['page'] );

		parent::tearDown();
	}

	/**
	 * Library submenus appear only on governing sites with connected brand sites.
	 */
	public function test_add_settings_page_requires_governing_site_with_brand_sites(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		$this->admin->add_settings_page();
		$this->assertArrayNotHasKey( SettingsAdmin::MENU_SLUG, $GLOBALS['submenu'] ?? [] );

		Settings::set_shared_sites(
			[
				[
					'name' => 'Brand',
					'url'  => 'https://brand.example',
				],
			]
		);
		$this->admin->add_settings_page();

		$this->assertSame(
			[ Admin::PATTERN_REDIRECT_SCREEN, Admin::TEMPLATE_REDIRECT_SCREEN ],
			array_column( $GLOBALS['submenu'][ SettingsAdmin::MENU_SLUG ], 2 )
		);
	}

	/**
	 * Brand sites register shared templates and patterns, and create shared template parts once.
	 */
	public function test_create_template_registers_shared_items_on_brand_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		update_option(
			Constants::ONEDESIGN_SHARED_TEMPLATES,
			[
				[
					'id'      => self::TEMPLATE_NAME,
					'slug'    => 'single-shared',
					'title'   => 'Shared Single',
					'content' => '<!-- wp:paragraph --><p>Shared</p><!-- /wp:paragraph -->',
				],
			]
		);
		update_option(
			Constants::ONEDESIGN_SHARED_PATTERNS,
			[
				[
					'slug'    => self::PATTERN_NAME,
					'title'   => 'Hero',
					'content' => '<!-- wp:heading --><h2>Hero</h2><!-- /wp:heading -->',
				],
			]
		);
		update_option(
			Constants::ONEDESIGN_SHARED_TEMPLATE_PARTS,
			[
				[
					'slug'        => 'governing-brand-header',
					'title'       => 'Shared Header',
					'area'        => 'header',
					'description' => 'From governing',
					'content'     => '<!-- wp:site-title /-->',
				],
			]
		);

		$this->admin->create_template();

		$this->assertSame( 'Shared Single', \WP_Block_Templates_Registry::get_instance()->get_registered( self::TEMPLATE_NAME )->title );
		$this->assertSame( 'Hero', \WP_Block_Patterns_Registry::get_instance()->get_registered( self::PATTERN_NAME )['title'] );

		// Simulate the next request: registries are rebuilt, template part posts persist.
		unregister_block_template( self::TEMPLATE_NAME );
		unregister_block_pattern( self::PATTERN_NAME );
		$this->admin->create_template();

		$parts = get_posts(
			[
				'post_type'   => 'wp_template_part',
				'name'        => 'governing-brand-header',
				'post_status' => 'any',
			]
		);
		$this->assertCount( 1, $parts, 'Template part should only be created once' );
		$this->assertSame( 'header', get_post_meta( $parts[0]->ID, '_wp_template_part_area', true ) );
		$this->assertSame( get_stylesheet(), wp_get_object_terms( $parts[0]->ID, 'wp_theme', [ 'fields' => 'names' ] )[0] );
		$this->assertSame( [ $parts[0]->ID ], get_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS ) );
	}

	/**
	 * Governing sites never register the shared items they push.
	 */
	public function test_create_template_does_nothing_on_governing_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		update_option(
			Constants::ONEDESIGN_SHARED_TEMPLATES,
			[
				[
					'id'   => self::TEMPLATE_NAME,
					'slug' => 'single-shared',
				],
			]
		);

		$this->admin->create_template();

		$this->assertFalse( \WP_Block_Templates_Registry::get_instance()->is_registered( self::TEMPLATE_NAME ) );
		$this->assertFalse( get_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS ) );
	}

	/**
	 * Patterns received from the governing site are registered with their categories; incomplete ones are skipped.
	 */
	public function test_register_block_patterns_if_not_exist(): void {
		update_option(
			Constants::ONEDESIGN_BRAND_SITE_PATTERNS,
			[
				'brand/cta'        => [
					'title'      => 'CTA',
					'content'    => '<!-- wp:buttons /-->',
					'categories' => [ 'call-to-action-shared' ],
					'postTypes'  => [ 'page' ],
				],
				'brand/no-content' => [ 'title' => 'Empty' ],
			]
		);

		$this->admin->register_block_patterns_if_not_exist();
		$this->admin->register_block_patterns_if_not_exist();

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$this->assertSame( [ 'page' ], $registry->get_registered( 'brand/cta' )['postTypes'] );
		$this->assertFalse( $registry->is_registered( 'brand/no-content' ) );
		$this->assertNotEmpty( term_exists( 'call-to-action-shared', 'wp_pattern_category' ) );
	}

	/**
	 * The template library editor allows no blocks; other post types keep their setting.
	 */
	public function test_allowed_block_types_disables_blocks_for_template_library(): void {
		$template_context = new \WP_Block_Editor_Context( [ 'post' => self::factory()->post->create_and_get( [ 'post_type' => Template::get_slug() ] ) ] );
		$post_context     = new \WP_Block_Editor_Context( [ 'post' => self::factory()->post->create_and_get() ] );

		$this->assertSame( [], $this->admin->allowed_block_types( true, $template_context ) );
		$this->assertSame( [ 'core/paragraph' ], $this->admin->allowed_block_types( [ 'core/paragraph' ], $post_context ) );
	}

	/**
	 * Library menu pages create the singleton library post once, then always open it.
	 *
	 * @param string $method    Redirect handler.
	 * @param string $page      Requested admin page.
	 * @param string $post_type Library post type.
	 *
	 * @dataProvider redirect_provider
	 */
	#[DataProvider( 'redirect_provider' )]
	public function test_library_page_redirects_to_single_library_post( string $method, string $page, string $post_type ): void {
		$this->admin->$method();
		$this->assertSame(
			[],
			get_posts(
				[
					'post_type'   => $post_type,
					'post_status' => 'any',
				]
			),
			'Nothing happens without the page param'
		);

		$this->intercept_redirects();
		$_GET['page'] = $page;

		$first = $this->catch_redirect( [ $this->admin, $method ] );
		$posts = get_posts(
			[
				'post_type'   => $post_type,
				'post_status' => 'draft',
			]
		);

		$this->assertCount( 1, $posts );
		$this->assertSame( admin_url( 'post.php?post=' . $posts[0]->ID . '&action=edit' ), $first );
		$this->assertSame( $first, $this->catch_redirect( [ $this->admin, $method ] ), 'Existing post should be reused' );
	}

	/**
	 * Editor button templates are printed only on their library's editor screen.
	 */
	public function test_editor_buttons_printed_only_on_matching_screen(): void {
		( new Pattern() )->register_post_type();
		( new Template() )->register_post_type();

		set_current_screen( Pattern::get_slug() );
		ob_start();
		$this->admin->print_pattern_library_button_in_editor_js_template();
		$this->admin->add_templates_button_to_editor();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="pattern-library-gutenberg-button"', $output );
		$this->assertStringNotContainsString( 'id="onedesign-template-button"', $output );

		set_current_screen( Template::get_slug() );
		ob_start();
		$this->admin->add_templates_button_to_editor();
		$this->assertStringContainsString( 'id="onedesign-template-button"', (string) ob_get_clean() );
	}

	/**
	 * Core block patterns are removed so only shared patterns show up.
	 */
	public function test_remove_core_block_patterns(): void {
		add_theme_support( 'core-block-patterns' );

		$this->admin->remove_core_block_patterns();

		$this->assertFalse( current_theme_supports( 'core-block-patterns' ) );
	}

	/**
	 * Provides the redirect handlers.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function redirect_provider(): array {
		return [
			'pattern library'  => [ 'handle_pattern_library_redirect', Admin::PATTERN_REDIRECT_SCREEN, Pattern::get_slug() ],
			'plugin menu'      => [ 'handle_pattern_library_redirect', SettingsAdmin::MENU_SLUG, Pattern::get_slug() ],
			'template library' => [ 'templates_page_redirection', Admin::TEMPLATE_REDIRECT_SCREEN, Template::get_slug() ],
		];
	}

	/**
	 * Run a callback that redirects and return the redirect location.
	 *
	 * @param callable $callback Callback expected to redirect.
	 */
	private function catch_redirect( callable $callback ): string {
		try {
			$callback();
		} catch ( \RuntimeException $e ) {
			return $e->getMessage();
		}

		$this->fail( 'Expected a redirect.' );
	}
}
