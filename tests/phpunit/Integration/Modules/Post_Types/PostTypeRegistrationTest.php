<?php
/**
 * Post type and meta registration unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Post_Types
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Post_Types;

use OneDesign\Modules\Post_Types\Meta;
use OneDesign\Modules\Post_Types\Pattern;
use OneDesign\Modules\Post_Types\Template;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the Pattern/Template post types and their meta.
 */
#[CoversClass( Pattern::class )]
#[CoversClass( Template::class )]
#[CoversClass( Meta::class )]
final class PostTypeRegistrationTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->preserve_globals( [ 'wp_post_types', 'wp_rest_server' ] );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		unregister_post_meta( Pattern::get_slug(), 'brand_site' );
		unregister_post_type( Pattern::get_slug() );
		unregister_post_type( Template::get_slug() );

		parent::tearDown();
	}

	/**
	 * Library post types are admin/REST-only and never publicly queryable.
	 *
	 * @param class-string<\OneDesign\Modules\Post_Types\Pattern|\OneDesign\Modules\Post_Types\Template> $class_name    Post type class.
	 * @param bool                                                                                       $has_revisions Whether revisions are supported.
	 */
	#[DataProvider( 'post_type_provider' )]
	public function test_register_post_type_is_admin_only( string $class_name, bool $has_revisions ): void {
		( new $class_name() )->register_post_type();

		$post_type = get_post_type_object( $class_name::get_slug() );

		$this->assertInstanceOf( \WP_Post_Type::class, $post_type );
		$this->assertFalse( $post_type->public );
		$this->assertFalse( $post_type->publicly_queryable );
		$this->assertTrue( $post_type->show_ui );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertTrue( post_type_supports( $class_name::get_slug(), 'custom-fields' ) );
		$this->assertSame( $has_revisions, post_type_supports( $class_name::get_slug(), 'revisions' ) );
	}

	/**
	 * The brand_site meta is saved through REST as a list of integers.
	 */
	public function test_brand_site_meta_is_writable_via_rest_as_integers(): void {
		( new Pattern() )->register_post_type();
		( new Meta() )->register_custom_meta();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$post_id = self::factory()->post->create( [ 'post_type' => Pattern::get_slug() ] );

		// Rebuild the REST server so the freshly registered post type gets its routes.
		$GLOBALS['wp_rest_server'] = null;

		$request = new \WP_REST_Request( 'POST', '/wp/v2/' . Pattern::get_slug() . '/' . $post_id );
		$request->set_body_params( [ 'meta' => [ 'brand_site' => [ '3', 7 ] ] ] );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 3, 7 ], get_post_meta( $post_id, 'brand_site', true ) );
		$this->assertSame( [ 3, 7 ], $response->get_data()['meta']['brand_site'] );
	}

	/**
	 * Users who cannot edit posts are denied by the meta auth callback.
	 */
	public function test_brand_site_meta_requires_edit_posts(): void {
		( new Pattern() )->register_post_type();
		( new Meta() )->register_custom_meta();
		$post_id = self::factory()->post->create( [ 'post_type' => Pattern::get_slug() ] );

		$auth = get_registered_meta_keys( 'post', Pattern::get_slug() )['brand_site']['auth_callback'];

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $auth( false, 'brand_site', $post_id ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertTrue( $auth( false, 'brand_site', $post_id ) );
	}

	/**
	 * Provides the library post type classes.
	 *
	 * @return array<string, array{class-string, bool}>
	 */
	public static function post_type_provider(): array {
		return [
			'pattern'  => [ Pattern::class, true ],
			'template' => [ Template::class, false ],
		];
	}
}
