<?php
/**
 * Tests for the patterns a brand site registers.
 *
 * @package OneDesign\Tests\Integration\Modules\Library
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Library;

use OneDesign\Modules\Library\Patterns;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class - PatternsTest
 */
#[CoversClass( Patterns::class )]
final class PatternsTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		update_option(
			Patterns::PATTERNS_OPTION_KEY,
			[
				'theme/hero' => [
					'name'       => 'theme/hero',
					'title'      => 'Hero',
					'content'    => '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->',
					'categories' => [],
				],
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'theme/hero' ) ) {
			unregister_block_pattern( 'theme/hero' );
		}

		parent::tear_down();
	}

	/**
	 * Only a brand site registers the patterns it received, so a site that stops being one stops showing them.
	 *
	 * @param string $site_type The site type, or '' for a site that has none yet.
	 * @param bool   $expected  Whether they're registered.
	 */
	#[DataProvider( 'site_types' )]
	public function test_only_a_brand_site_registers_what_it_received( string $site_type, bool $expected ): void {
		update_option( Settings::OPTION_SITE_TYPE, $site_type );

		( new Patterns() )->register_received_patterns();

		$this->assertSame( $expected, \WP_Block_Patterns_Registry::get_instance()->is_registered( 'theme/hero' ) );
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

	/**
	 * A brand site's own pattern keeps its name over one it received with the same name.
	 */
	public function test_a_brand_site_pattern_wins_over_a_received_one(): void {
		$this->set_up_brand_site();
		register_block_pattern(
			'theme/hero',
			[
				'title'   => 'Own Hero',
				'content' => '<!-- wp:paragraph --><p>Own</p><!-- /wp:paragraph -->',
			]
		);

		( new Patterns() )->register_received_patterns();

		$this->assertSame( 'Own Hero', \WP_Block_Patterns_Registry::get_instance()->get_registered( 'theme/hero' )['title'] );
	}
}
