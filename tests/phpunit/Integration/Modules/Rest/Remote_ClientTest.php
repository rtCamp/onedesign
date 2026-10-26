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
use OneDesign\Utils;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the requests between a governing site and its brand sites.
 */
#[CoversClass( Remote_Client::class )]
class Remote_ClientTest extends TestCase {
	/**
	 * Disconnecting a brand site sends it an authenticated DELETE, and reports whether it confirmed.
	 */
	public function test_disconnect_brand_site_sends_authenticated_delete(): void {
		$sent   = [];
		$filter = static function ( $preempt, array $args, string $url ) use ( &$sent ): array { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$sent[] = [
				'url'  => $url,
				'args' => $args,
			];

			return [
				'response' => [
					// Brand B no longer knows this governing site.
					'code'    => str_contains( $url, 'b.example.com' ) ? 404 : 200,
					'message' => '',
				],
				'body'     => '',
				'headers'  => [],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$confirmed = Remote_Client::disconnect_brand_site(
			[
				'url'     => 'https://a.example.com/',
				'api_key' => 'key-a',
			]
		);
		$refused   = Remote_Client::disconnect_brand_site(
			[
				'url'     => 'https://b.example.com/',
				'api_key' => 'key-b',
			]
		);

		remove_filter( 'pre_http_request', $filter );

		$this->assertTrue( $confirmed );
		$this->assertFalse( $refused );
		$this->assertSame( 'https://a.example.com/wp-json/onedesign/v1/governing-site', $sent[0]['url'] );
		$this->assertSame( 'DELETE', $sent[0]['args']['method'] );
		$this->assertSame( 'key-a', $sent[0]['args']['headers']['X-OneDesign-Token'] );
		$this->assertSame( Utils::get_current_site_url(), $sent[0]['args']['headers']['X-OneDesign-Site-URL'] );
	}

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
