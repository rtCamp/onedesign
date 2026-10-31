<?php
/**
 * Tests for the templates a brand site registers.
 *
 * @package OneDesign\Tests\Integration\Modules\Library
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Library;

use OneDesign\Modules\Library\Templates;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class - TemplatesTest
 */
#[CoversClass( Templates::class )]
final class TemplatesTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		update_option(
			Templates::TEMPLATES_OPTION_KEY,
			[
				'theme//landing' => [
					'id'          => 'gov-brand//landing',
					'original_id' => 'theme//landing',
					'title'       => 'Landing',
					'description' => '',
					'content'     => '<!-- wp:pattern {"slug":"gov-brand-cta"} /-->',
				],
			]
		);
		update_option(
			Templates::TEMPLATE_PATTERNS_OPTION_KEY,
			[
				'theme/cta' => [
					'name'       => 'gov-brand-cta',
					'title'      => 'CTA',
					'content'    => '<!-- wp:paragraph --><p>Talk to us</p><!-- /wp:paragraph -->',
					'categories' => [],
				],
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		if ( \WP_Block_Templates_Registry::get_instance()->is_registered( 'gov-brand//landing' ) ) {
			unregister_block_template( 'gov-brand//landing' );
		}
		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'gov-brand-cta' ) ) {
			unregister_block_pattern( 'gov-brand-cta' );
		}

		parent::tear_down();
	}

	/**
	 * Only a brand site registers the templates it received and the patterns they use, so a site that stops being one stops showing them.
	 *
	 * @param string $site_type The site type, or '' for a site that has none yet.
	 * @param bool   $expected  Whether they're registered.
	 */
	#[DataProvider( 'site_types' )]
	public function test_only_a_brand_site_registers_what_it_received( string $site_type, bool $expected ): void {
		update_option( Settings::OPTION_SITE_TYPE, $site_type );

		( new Templates() )->register_received_templates();

		$this->assertSame( $expected, \WP_Block_Templates_Registry::get_instance()->is_registered( 'gov-brand//landing' ) );
		$this->assertSame( $expected, \WP_Block_Patterns_Registry::get_instance()->is_registered( 'gov-brand-cta' ) );
	}

	/**
	 * Each site type, and whether it registers what brand sites receive.
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function site_types(): array {
		return [
			'brand site'     => [ Settings::SITE_TYPE_CONSUMER, true ],
			'governing site' => [ Settings::SITE_TYPE_GOVERNING, false ],
			'no type yet'    => [ '', false ],
		];
	}
}
