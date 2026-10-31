<?php
/**
 * Shared utilities functions for internal plugin use.
 *
 * Utilities are a domain antipattern - where ever possible, colocate methods with their related classes.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign;

/**
 * Class - Utils
 */
final class Utils {
	/**
	 * Normalize a URL by trimming whitespace and ensuring it ends with a trailing slash.
	 *
	 * @param string $url The URL to normalize.
	 */
	public static function normalize_url( string $url ): string {
		return (string) trailingslashit( trim( $url ) );
	}

	/**
	 * Gets the normalized URL that identifies the current site across the network.
	 *
	 * Uses `get_option( 'home' )` since `get_home_url()` changes the scheme per the current request.
	 */
	public static function get_current_site_url(): string {
		return self::normalize_url( (string) get_option( 'home' ) );
	}

	/**
	 * Logs a message to the PHP error log when debugging is enabled.
	 *
	 * @param string $message The message to log. Will be prefixed with the plugin name.
	 */
	public static function log( string $message ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only logging.
		error_log( 'OneDesign: ' . $message );
	}
}
