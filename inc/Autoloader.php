<?php
/**
 * Autoloader for PHP classes inside plugin.
 *
 * Wraps the Composer autoloader to provide graceful failure if it is missing.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign;

/**
 * Class - Autoloader
 */
final class Autoloader {
	/**
	 * Whether the autoloader has been loaded.
	 *
	 * @var bool
	 */
	protected static bool $is_loaded = false;

	/**
	 * Attempts to autoload the Composer dependencies.
	 */
	public static function autoload(): bool {
		// If we're not *supposed* to autoload anything, then return true.
		if ( defined( 'ONEDESIGN_AUTOLOAD' ) && false === ONEDESIGN_AUTOLOAD ) {
			return true;
		}

		if ( self::$is_loaded ) {
			return self::$is_loaded;
		}

		$autoloader      = ONEDESIGN_DIR . 'vendor/autoload.php';
		self::$is_loaded = self::require_autoloader( $autoloader );

		return self::$is_loaded;
	}

	/**
	 * Attempts to load the autoloader file, if it exists.
	 *
	 * @param string $autoloader_file The path to the autoloader file.
	 */
	protected static function require_autoloader( string $autoloader_file ): bool {
		if ( ! is_readable( $autoloader_file ) ) {
			self::missing_autoloader_notice();
			return false;
		}

		return (bool) require_once $autoloader_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Autoloader is a Composer file.
	}

	/**
	 * Displays a notice if the autoloader is missing.
	 */
	protected static function missing_autoloader_notice(): void {
		$hooks = [
			'admin_notices',
			'network_admin_notices',
		];

		foreach ( $hooks as $hook ) {
			add_action(
				$hook,
				static function (): void {
					$error_message = __( 'OneDesign: The Composer autoloader was not found. If you installed the plugin from the GitHub source code, make sure to run `composer install`.', 'onedesign' );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( esc_html( $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is a development notice.
					}

					wp_admin_notice(
						$error_message,
						[
							'type'    => 'error',
							'dismiss' => false,
						]
					);
				}
			);
		}
	}
}
