<?php
/**
 * Multisite_Controller unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Multisite\Settings as MU_Settings;
use OneDesign\Modules\Rest\Multisite_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use WP_REST_Request;

/**
 * Tests for configuring governing and brand sites across a network.
 */
#[CoversClass( Multisite_Controller::class )]
#[Group( 'ms-required' )]
final class Multisite_ControllerTest extends TestCase {
	/**
	 * Controller under test.
	 */
	private Multisite_Controller $controller;

	/**
	 * Brand site blog ID.
	 */
	private int $brand_id;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$this->controller = new Multisite_Controller();
		$this->brand_id   = self::factory()->blog->create( [ 'title' => 'Brand Site' ] );
	}

	/**
	 * Choosing a governing site marks it governing and every other site as a brand site with a fresh key.
	 */
	public function test_set_governing_site_assigns_site_types(): void {
		update_blog_option( $this->brand_id, Settings::OPTION_CONSUMER_PARENT_SITE_URL, 'https://stale.example' );

		$this->assertSame( 400, $this->controller->set_multisite_governing_site( $this->make_request( [ 'governing_site_id' => 'abc' ] ) )->get_error_data()['status'] );

		$response = $this->controller->set_multisite_governing_site( $this->make_request( [ 'governing_site_id' => 1 ] ) );

		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 1, $this->controller->get_multisite_governing_site()->get_data()['governing_site'] );
		$this->assertSame( Settings::SITE_TYPE_GOVERNING, get_option( Settings::OPTION_SITE_TYPE ) );
		$this->assertSame( Settings::SITE_TYPE_CONSUMER, get_blog_option( $this->brand_id, Settings::OPTION_SITE_TYPE ) );
		$this->assertNotEmpty( get_blog_option( $this->brand_id, Settings::OPTION_CONSUMER_API_KEY ) );
		$this->assertFalse( get_blog_option( $this->brand_id, Settings::OPTION_CONSUMER_PARENT_SITE_URL ), 'Stale pairing is cleared' );
	}

	/**
	 * Adding sites pairs them with the governing site and lists them with their API keys.
	 */
	public function test_add_multisite_sites_pairs_brand_sites(): void {
		$request = $this->make_request( [ 'site_ids' => [ $this->brand_id ] ] );
		$this->assertSame( 'no_governing_site', $this->controller->add_multisite_sites( $request )->get_error_code() );

		MU_Settings::set_multisite_governing_site_id( 1 );
		$this->assertTrue( $this->controller->add_multisite_sites( $request )->get_data()['success'] );

		switch_to_blog( $this->brand_id );
		$brand_key  = Settings::get_api_key();
		$parent_url = Settings::get_parent_site_url();
		$is_brand   = Settings::is_consumer_site();
		restore_current_blog();

		$this->assertTrue( $is_brand );
		$this->assertSame( untrailingslashit( get_site_url( 1 ) ), $parent_url );

		$shared = Settings::get_shared_sites()[ trailingslashit( get_home_url( $this->brand_id ) ) ];
		$this->assertSame( (string) $this->brand_id, $shared['id'] );
		$this->assertSame( 'Brand Site', $shared['name'] );
		$this->assertSame( $brand_key, $shared['api_key'] );
	}

	/**
	 * All network sites are returned.
	 */
	public function test_get_all_multisite_sites(): void {
		$ids = array_column( $this->controller->get_all_multisite_sites()->get_data()['sites'], 'id' );

		$this->assertContains( (string) $this->brand_id, $ids );
		$this->assertContains( '1', $ids );
	}

	/**
	 * Build a request with the given params.
	 *
	 * @param array<string, mixed> $params Request params.
	 */
	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request();
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}
}
