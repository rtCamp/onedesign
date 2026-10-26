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
}
