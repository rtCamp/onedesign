<?php
/**
 * Uninstall tests.
 *
 * @package OneDesign\Tests\Integration
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration;

use OneDesign\Modules\Library\Patterns;
use OneDesign\Modules\Library\Templates;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Runs uninstall.php, which is a script rather than a class.
 */
#[CoversNothing]
final class UninstallTest extends TestCase {
	/**
	 * Uninstalling a brand site removes the options that hold the connection between the sites and what it received, but keeps the posts it created, since its pages may use them.
	 *
	 * The script declares functions, so it can only be run once per test run.
	 */
	public function test_uninstall_deletes_the_connection_options_and_keeps_the_posts(): void {
		$options = [
			Settings::OPTION_CONSUMER_PARENT_SITE_URL,
			Settings::OPTION_CONSUMER_API_KEY,
			Patterns::PATTERNS_OPTION_KEY,
			Templates::TEMPLATE_PATTERNS_OPTION_KEY,
			Templates::TEMPLATES_OPTION_KEY,
			Templates::POST_IDS_OPTION_KEY,
		];

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( 'https://governing.example.com' );
		Settings::get_api_key();

		// A synced pattern a received template uses.
		$synced_pattern_id = self::factory()->post->create( [ 'post_type' => 'wp_block' ] );
		update_option( Templates::POST_IDS_OPTION_KEY, [ $synced_pattern_id ] );
		update_option( Templates::TEMPLATES_OPTION_KEY, [ [ 'id' => 'governing//home' ] ] );
		update_option( Patterns::PATTERNS_OPTION_KEY, [ 'hero' => [ 'name' => 'hero' ] ] );
		update_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY, [ 'theme/cta' => [ 'name' => 'governing-brand-cta' ] ] );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'onedesign/onedesign.php' );
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant, WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- Runs the script under test.
		include dirname( __DIR__, 3 ) . '/uninstall.php';

		foreach ( $options as $option ) {
			$this->assertFalse( get_option( $option ), $option . ' should be deleted.' );
		}

		$this->assertInstanceOf( \WP_Post::class, get_post( $synced_pattern_id ) );
	}
}
