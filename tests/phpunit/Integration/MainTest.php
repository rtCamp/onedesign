<?php
/**
 * Main unit tests.
 *
 * @package OneDesign\Tests\Integration
 */

declare(strict_types = 1);

namespace OneDesign\Tests\Integration;

use OneDesign\Main;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Main bootstrap class.
 */
#[CoversClass( \OneDesign\Main::class )]
final class MainTest extends TestCase {
	/**
	 * @var string|false|null
	 */
	private string|false|null $original_permalink_structure = null;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_permalink_structure = get_option( 'permalink_structure' );

		$this->reset_main_singleton();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		$this->reset_main_singleton();

		null === $this->original_permalink_structure
			? delete_option( 'permalink_structure' )
			: update_option( 'permalink_structure', $this->original_permalink_structure );

		parent::tearDown();
	}

	/**
	 * Ensures instance returns the same object.
	 */
	public function test_instance_returns_singleton(): void {
		update_option( 'permalink_structure', '/%postname%/' );

		$this->assertSame( Main::instance(), Main::instance() );
	}

	/**
	 * Ensures setup does not load registrable classes when permalinks are disabled.
	 */
	public function test_setup_does_not_load_when_permalinks_disabled(): void {
		update_option( 'permalink_structure', '' );

		Main::instance();

		$this->expectOutputRegex( '/OneDesign: The plugin requires pretty permalinks to be enabled./' );
		do_action( 'admin_notices' );

		$this->expectOutputRegex( '/OneDesign: The plugin requires pretty permalinks to be enabled./' );
		do_action( 'network_admin_notices' );
	}

	/**
	 * Reset the Main singleton.
	 */
	private function reset_main_singleton(): void {
		$reflection = new \ReflectionProperty( Main::class, 'instance' );
		$reflection->setValue( null, null );
	}
}
