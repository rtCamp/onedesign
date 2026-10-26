<?php
/**
 * Tests for Remote_Client.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Remote_Client;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the requests between a governing site and its brand sites.
 */
#[CoversClass( Remote_Client::class )]
class Remote_ClientTest extends TestCase {
	/**
	 * A brand site's own error message reaches the governing site, whatever the status.
	 */
	public function test_brand_site_errors_carry_the_brand_message(): void {
		$filter = static fn (): array => [
			'response' => [
				'code'    => 404,
				'message' => 'Not Found',
			],
			'body'     => '{"message":"No route was found"}',
			'headers'  => [],
			'cookies'  => [],
		];
		add_filter( 'pre_http_request', $filter );

		$result = Remote_Client::request_brand_site(
			[
				'url'     => 'https://brand.example.com/',
				'api_key' => 'key',
			],
			'patterns'
		);

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'No route was found', $result->get_error_message() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	/**
	 * A brand site that rejects the stored key, after its key was regenerated, asks the admin for the new key.
	 */
	public function test_brand_site_rejecting_the_stored_key_asks_for_the_new_key(): void {
		$filter = static fn (): array => [
			'response' => [
				'code'    => 403,
				'message' => 'Forbidden',
			],
			'body'     => '{"code":"rest_forbidden","message":"Sorry, you are not allowed to do that."}',
			'headers'  => [],
			'cookies'  => [],
		];
		add_filter( 'pre_http_request', $filter );

		$result = Remote_Client::request_brand_site(
			[
				'url'     => 'https://brand.example.com/',
				'api_key' => 'old-key',
			],
			'patterns'
		);

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'onedesign_bad_key', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * A brand site that can't be reached is a bad gateway, not an error on this site.
	 */
	public function test_unreachable_brand_site_is_a_bad_gateway(): void {
		$filter = static fn (): \WP_Error => new \WP_Error( 'http_request_failed', 'cURL error 7' );
		add_filter( 'pre_http_request', $filter );

		$result = Remote_Client::request_brand_site(
			[
				'url'     => 'https://brand.example.com/',
				'api_key' => 'key',
			],
			'patterns'
		);

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
		$this->assertSame( [ 'status' => 502 ], $result->get_error_data() );
	}
}
