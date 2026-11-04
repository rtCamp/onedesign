<?php
/**
 * Tests for Remote_Client.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Remote_Client;
use OneDesign\Modules\Settings\Settings;
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
	 * Deregistering is only possible from a brand site.
	 */
	public function test_deregister_from_governing_site_returns_error_when_not_consumer_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$result = Remote_Client::deregister_from_governing_site();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'onedesign_unauthorized_site', $result->get_error_code() );
	}

	/**
	 * Sends a DELETE to the governing site's brand-site route, authenticated as this brand site.
	 */
	public function test_deregister_from_governing_site_sends_authenticated_delete(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( 'https://governing.example.com' );
		$api_key = Settings::regenerate_api_key();

		$captured = [];
		$filter   = static function ( $preempt, $args, $url ) use ( &$captured ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$captured = [
				'url'  => $url,
				'args' => $args,
			];
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => '',
				'headers'  => [],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$result = Remote_Client::deregister_from_governing_site();

		remove_filter( 'pre_http_request', $filter );

		$this->assertTrue( $result );
		$this->assertSame( 'https://governing.example.com/wp-json/onedesign/v1/brand-site', $captured['url'] );
		$this->assertSame( 'DELETE', $captured['args']['method'] );
		$this->assertSame( $api_key, $captured['args']['headers']['X-OneDesign-Token'] );
		$this->assertSame( Utils::get_current_site_url(), $captured['args']['headers']['X-OneDesign-Site-URL'] );
		$this->assertArrayNotHasKey( 'Origin', $captured['args']['headers'] );
	}

	/**
	 * A rejected token means the governing site no longer lists this site with its current key.
	 */
	public function test_deregister_from_governing_site_reports_unrecognized_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( 'https://governing.example.com' );
		Settings::regenerate_api_key();

		$filter = static function ( $preempt, $args, $url ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			if ( false === strpos( $url, '/brand-site' ) ) {
				return $preempt;
			}
			return [
				'response' => [
					'code'    => 401,
					'message' => 'Unauthorized',
				],
				'body'     => '',
				'headers'  => [],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$result = Remote_Client::deregister_from_governing_site();

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'onedesign_unrecognized_site', $result->get_error_code() );
	}

	/**
	 * Any other non-200 response from the governing site returns the failed-to-connect error.
	 */
	public function test_deregister_from_governing_site_returns_error_on_non_200(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( 'https://governing.example.com' );
		Settings::regenerate_api_key();

		$filter = static function ( $preempt, $args, $url ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			if ( false === strpos( $url, '/brand-site' ) ) {
				return $preempt;
			}
			return [
				'response' => [
					'code'    => 500,
					'message' => 'Internal Server Error',
				],
				'body'     => '',
				'headers'  => [],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$result = Remote_Client::deregister_from_governing_site();

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'onedesign_rest_failed_to_connect', $result->get_error_code() );
	}

	/**
	 * A WP_Error from the remote layer is propagated unchanged.
	 */
	public function test_deregister_from_governing_site_propagates_wp_error(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( 'https://governing.example.com' );
		Settings::regenerate_api_key();

		$filter = static function ( $preempt, $args, $url ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			if ( false === strpos( $url, '/brand-site' ) ) {
				return $preempt;
			}
			return new \WP_Error( 'http_request_failed', 'cURL error 7' );
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$result = Remote_Client::deregister_from_governing_site();

		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
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
