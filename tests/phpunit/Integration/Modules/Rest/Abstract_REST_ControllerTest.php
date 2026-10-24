<?php
/**
 * Abstract_REST_Controller unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Basic_Options_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Tests for the cross-site API permission check.
 */
#[CoversClass( Abstract_REST_Controller::class )]
final class Abstract_REST_ControllerTest extends TestCase {
	private const GOVERNING_URL = 'https://governing.example';

	/**
	 * Controller used to exercise the shared permission logic.
	 */
	private Basic_Options_Controller $controller;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->controller = new Basic_Options_Controller();
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
	}

	/**
	 * Governing sites authorize by capability instead of token.
	 */
	public function test_governing_site_requires_manage_options(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertFalse( $this->controller->check_api_permissions( $this->make_request( Settings::get_api_key(), get_site_url() ) ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( $this->controller->check_api_permissions( new WP_REST_Request( 'GET', '/onedesign/v1/health-check' ) ) );
	}

	/**
	 * Brand sites reject missing or wrong tokens, and valid tokens with no origin or user agent.
	 */
	public function test_brand_site_rejects_invalid_token_or_anonymous_source(): void {
		$this->assertFalse( $this->controller->check_api_permissions( $this->make_request( '', get_site_url() ) ) );
		$this->assertFalse( $this->controller->check_api_permissions( $this->make_request( 'wrong-key', get_site_url() ) ) );
		$this->assertFalse( $this->controller->check_api_permissions( $this->make_request( Settings::get_api_key() ) ) );
	}

	/**
	 * A valid token from the brand site's own host (same port) is accepted.
	 */
	public function test_brand_site_accepts_same_domain_requests(): void {
		$api_key = Settings::get_api_key();
		$parts   = wp_parse_url( get_site_url() );

		$this->assertTrue( $this->controller->check_api_permissions( $this->make_request( $api_key, get_site_url() ) ) );
		$this->assertFalse(
			$this->controller->check_api_permissions( $this->make_request( $api_key, $parts['scheme'] . '://' . $parts['host'] . ':8443' ) ),
			'Same host on a different port is a different origin'
		);
	}

	/**
	 * Without a governing site, only a health check from the settings page pairs the sites.
	 */
	public function test_first_health_check_from_settings_pairs_governing_site(): void {
		$api_key = Settings::get_api_key();

		$this->assertFalse( $this->controller->check_api_permissions( $this->make_request( $api_key, self::GOVERNING_URL, '/onedesign/v1/site-type' ) ) );
		$this->assertFalse( $this->controller->check_api_permissions( $this->make_request( $api_key, self::GOVERNING_URL ) ), 'Source header is required' );
		$this->assertNull( Settings::get_parent_site_url() );

		$request = $this->make_request( $api_key, self::GOVERNING_URL );
		$request->set_header( 'X-OneDesign-Source', 'Settings' );

		$this->assertTrue( $this->controller->check_api_permissions( $request ) );
		$this->assertSame( self::GOVERNING_URL, Settings::get_parent_site_url() );
	}

	/**
	 * Once paired, only the governing site's origin (or user agent) is accepted.
	 */
	public function test_paired_brand_site_accepts_only_governing_site(): void {
		$api_key = Settings::get_api_key();
		Settings::set_parent_site_url( self::GOVERNING_URL );

		$this->assertTrue( $this->controller->check_api_permissions( $this->make_request( $api_key, self::GOVERNING_URL, '/onedesign/v1/receive-patterns' ) ) );
		$this->assertFalse( $this->controller->check_api_permissions( $this->make_request( $api_key, 'https://attacker.example' ) ) );

		$request = $this->make_request( $api_key );
		$request->set_header( 'User-Agent', 'WordPress/6.8; ' . self::GOVERNING_URL );
		$this->assertTrue( $this->controller->check_api_permissions( $request ) );
	}

	/**
	 * Build a request carrying the OneDesign token and an optional origin.
	 *
	 * @param string $token  API token.
	 * @param string $origin Origin header.
	 * @param string $route  Request route.
	 */
	private function make_request( string $token, string $origin = '', string $route = '/onedesign/v1/health-check' ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_header( 'X-OneDesign-Token', $token );
		$request->set_header( 'Origin', $origin );

		return $request;
	}
}
