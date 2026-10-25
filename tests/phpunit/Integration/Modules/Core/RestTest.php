<?php
/**
 * Rest unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Core
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Core;

use OneDesign\Modules\Core\Rest;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Rest core module.
 */
#[CoversClass( Rest::class )]
final class RestTest extends TestCase {
	/**
	 * The OneDesign headers are appended once, keeping existing ones.
	 */
	public function test_allowed_cors_headers_appends_missing_headers_only(): void {
		$rest = new Rest();

		$this->assertSame(
			[ 'X-WP-Nonce', 'X-OneDesign-Token', 'X-OneDesign-Source' ],
			$rest->allowed_cors_headers( [ 'X-WP-Nonce' ] )
		);
		$this->assertSame(
			[ 'X-OneDesign-Source', 'X-OneDesign-Token' ],
			$rest->allowed_cors_headers( [ 'X-OneDesign-Source' ] ),
			'Headers already present should not be duplicated'
		);
	}
}
