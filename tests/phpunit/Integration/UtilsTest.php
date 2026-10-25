<?php
/**
 * Utils unit tests.
 *
 * @package OneDesign\Tests\Integration
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration;

use OneDesign\Tests\TestCase;
use OneDesign\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class UtilsTest
 */
#[CoversClass( \OneDesign\Utils::class )]
final class UtilsTest extends TestCase {
	/**
	 * Ensures provider cases normalize as expected.
	 *
	 * @param string $input    Raw input URL.
	 * @param string $expected Expected normalized URL.
	 */
	#[DataProvider( 'normalize_url_provider' )]
	public function test_normalize_url_with_data_provider( string $input, string $expected ): void {
		$this->assertSame( $expected, Utils::normalize_url( $input ) );
	}

	/**
	 * Provides URL normalization cases.
	 *
	 * @return array<string, array{0:string, 1:string}>
	 */
	public static function normalize_url_provider(): array {
		return [
			'adds trailing slash'  => [
				'https://example.com',
				'https://example.com/',
			],
			'trims whitespace'     => [
				"\thttps://example.com/path \n",
				'https://example.com/path/',
			],
			'keeps trailing slash' => [
				'https://example.com/path/',
				'https://example.com/path/',
			],
			'query string'         => [
				'https://example.com?foo=bar',
				'https://example.com?foo=bar/',
			],
			'empty string'         => [
				'',
				'/',
			],
		];
	}

	/**
	 * The current site is identified by the normalized home URL.
	 */
	public function test_get_current_site_url_returns_the_normalized_home_url(): void {
		add_filter( 'pre_option_home', static fn (): string => 'http://example.org/blog' );

		$this->assertSame( 'http://example.org/blog/', Utils::get_current_site_url() );
	}
}
