<?php
/**
 * Plugin Name: OneDesign - Localhost Helper
 * Description: Rewrites localhost URLs to host.docker.internal for inter-container HTTP requests in wp-env Docker environments. Only affects URLs containing "onedesign/v1".
 * Version: 1.0.0
 * Author: rtCamp
 * License: GPL-2.0-or-later
 *
 * @package OneDesign\Dev
 */

declare( strict_types = 1 );

namespace OneDesign\Localhost_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bypass URL validation for onedesign endpoints.
add_filter( // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args
	'http_request_args',
	static function ( array $args, string $url ): array {
		if ( false === strpos( $url, 'onedesign/v1' ) ) {
			return $args;
		}

		$args['reject_unsafe_urls'] = false;
		return $args;
	},
	PHP_INT_MAX,
	2,
);

// Reroute localhost requests to host.docker.internal for onedesign endpoints.
add_filter(
	'pre_http_request',
	static function ( $preempt, array $args, string $url ) {
		if ( false === strpos( $url, 'onedesign/v1' ) || false === strpos( $url, '://localhost' ) ) {
			return $preempt;
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return $preempt;
		}

		$original_host = $parsed['host'] . ( isset( $parsed['port'] ) ? ':' . (int) $parsed['port'] : '' );

		$rewritten_url = str_replace( '://localhost', '://host.docker.internal', $url );

		if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}
		$args['headers']['Host'] = $original_host;
		return wp_remote_request( $rewritten_url, $args );
	},
	PHP_INT_MAX,
	3,
);
