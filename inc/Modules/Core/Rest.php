<?php
/**
 * Handles REST API behavior.
 *
 * @package OneDesign\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Modules\Core;

use OneDesign\Contracts\Interfaces\Registrable;

/**
 * Class REST
 */
final class Rest implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'rest_allowed_cors_headers', [ $this, 'allowed_cors_headers' ] );
	}

	/**
	 * Adds plugin CORS headers to those allowed in REST responses.
	 *
	 * @param array<int, string> $headers Existing headers.
	 *
	 * @return array<int, string> Modified headers.
	 */
	public function allowed_cors_headers( $headers ): array {
		$headers_to_add = [
			'X-OneDesign-Token',
			'X-OneDesign-Source',
		];

		// Only add headers that aren't already present.
		foreach ( $headers_to_add as $header ) {
			if ( in_array( $header, $headers, true ) ) {
				continue;
			}

			$headers[] = $header;
		}

		return $headers;
	}
}
