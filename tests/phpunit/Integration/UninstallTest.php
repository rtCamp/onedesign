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
use OneDesign\Modules\Multisite\Utils as MU_Utils;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Runs uninstall.php, which is a script rather than a class.
 */
#[CoversNothing]
final class UninstallTest extends TestCase {
	/**
	 * The requests uninstall sent, as [method, URL, token].
	 *
	 * @var array<int,array{string,string,?string}>
	 */
	private array $sent = [];

	/**
	 * Uninstalling a brand site deregisters it from the governing site and removes the options that hold the connection between the sites and what it received, but keeps the posts it created, since its pages may use them.
	 */
	public function test_uninstall_deregisters_the_brand_and_deletes_the_connection_options(): void {
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
		$api_key = Settings::get_api_key();

		// A synced pattern a received template uses.
		$synced_pattern_id = self::factory()->post->create( [ 'post_type' => 'wp_block' ] );
		update_option( Templates::POST_IDS_OPTION_KEY, [ $synced_pattern_id ] );
		update_option( Templates::TEMPLATES_OPTION_KEY, [ [ 'id' => 'governing//home' ] ] );
		update_option( Patterns::PATTERNS_OPTION_KEY, [ 'hero' => [ 'name' => 'hero' ] ] );
		update_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY, [ 'theme/cta' => [ 'name' => 'governing-brand-cta' ] ] );

		$this->record_requests();
		$this->run_uninstall();

		$this->assertSame(
			[
				[ 'DELETE', 'https://governing.example.com/wp-json/onedesign/v1/brand-site', $api_key ],
			],
			$this->sent
		);

		foreach ( $options as $option ) {
			$this->assertFalse( get_option( $option ), $option . ' should be deleted.' );
		}

		$this->assertInstanceOf( \WP_Post::class, get_post( $synced_pattern_id ) );
	}

	/**
	 * Uninstalling a governing site tells each of its brand sites to forget it, with that site's key.
	 */
	public function test_uninstall_disconnects_the_governing_sites_brand_sites(): void {
		$this->set_up_governing_site();

		$this->record_requests();
		$this->run_uninstall();

		$this->assertSame(
			[
				[ 'DELETE', 'https://brand.example.org/wp-json/onedesign/v1/governing-site', 'brand-key' ],
			],
			$this->sent
		);

		$this->assertFalse( get_option( Settings::OPTION_GOVERNING_SHARED_SITES ) );
	}

	/**
	 * A network uninstall sends nothing to the network's own sites, which are uninstalled in the same run, but still disconnects a brand site and deregisters from a governing site off the network.
	 */
	public function test_network_uninstall_only_contacts_sites_off_the_network(): void {
		$this->skipWithoutMultisite();

		// This site governs a brand site on the network and one off it.
		$this->set_up_governing_site();
		$network_brand_id = self::factory()->blog->create();
		MU_Utils::add_brand_site( get_current_blog_id(), $network_brand_id );

		// A brand site on the network with a governing site off it.
		$brand_id = self::factory()->blog->create();
		switch_to_blog( $brand_id );
		$this->set_up_brand_site();
		$brand_key = Settings::get_api_key();
		restore_current_blog();

		$this->record_requests();
		$this->run_uninstall();

		$this->assertSame(
			[
				[ 'DELETE', 'https://brand.example.org/wp-json/onedesign/v1/governing-site', 'brand-key' ],
				[ 'DELETE', 'https://governing.example.com/wp-json/onedesign/v1/brand-site', $brand_key ],
			],
			$this->sent
		);

		$this->assertFalse( get_option( Settings::OPTION_GOVERNING_SHARED_SITES ) );
		$this->assertFalse( get_blog_option( $network_brand_id, Settings::OPTION_CONSUMER_PARENT_SITE_URL ) );
		$this->assertFalse( get_blog_option( $brand_id, Settings::OPTION_CONSUMER_PARENT_SITE_URL ) );
	}

	/**
	 * A site with no governing site, like one nobody set up or a brand site whose governing site was uninstalled first, has nothing to deregister from, so uninstalling sends and logs nothing.
	 *
	 * @param string $site_type The site type, if any.
	 */
	#[DataProvider( 'unconnected_site_provider' )]
	public function test_uninstall_leaves_a_site_with_no_governing_site_alone( string $site_type ): void {
		update_option( Settings::OPTION_SITE_TYPE, $site_type );

		$log = (string) wp_tempnam();
		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Catches what uninstall logs.
		$previous_log = (string) ini_set( 'error_log', $log );

		$this->record_requests();
		$this->run_uninstall();

		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Restores the log.
		ini_set( 'error_log', $previous_log );

		$this->assertSame( [], $this->sent );
		$this->assertSame( '', (string) file_get_contents( $log ) ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
	}

	/**
	 * Sites with no governing site.
	 *
	 * @return array<string,array{string}>
	 */
	public static function unconnected_site_provider(): array {
		return [
			'no site type'            => [ '' ],
			'brand with no governing' => [ Settings::SITE_TYPE_CONSUMER ],
		];
	}

	/**
	 * Answers every request with a 200, and records it.
	 */
	private function record_requests(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, array $args, string $url ): array { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
				$this->sent[] = [ $args['method'] ?? 'GET', $url, $args['headers']['X-OneDesign-Token'] ?? null ];

				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '',
					'headers'  => [],
					'cookies'  => [],
				];
			},
			10,
			3
		);
	}

	/**
	 * Runs uninstall.php. The script declares functions, so it's included once per test run, and its uninstaller is called again after that.
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'onedesign/onedesign.php' );
		}

		if ( function_exists( 'OneDesign\run_uninstaller' ) ) {
			\OneDesign\run_uninstaller();
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant, WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- Runs the script under test.
		include dirname( __DIR__, 3 ) . '/uninstall.php';
	}
}
