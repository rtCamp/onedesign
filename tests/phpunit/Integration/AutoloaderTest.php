<?php
/**
 * Autoloader unit tests.
 *
 * @package OneDesign\Tests\Integration
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration;

use OneDesign\Autoloader;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( \OneDesign\Autoloader::class )]
/**
 * Class AutoloaderTest
 */
final class AutoloaderTest extends TestCase {
	/**
	 * Reset static state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_autoloader();
	}

	/**
	 * Clean up hooks and static state.
	 */
	protected function tearDown(): void {
		$this->reset_autoloader();

		parent::tearDown();
	}

	/**
	 * Ensures autoload succeeds when both Composer autoloaders exist.
	 */
	public function test_autoload_returns_true_when_autoloader_exists(): void {
		$this->assertTrue( Autoloader::autoload() );
		$property = new \ReflectionProperty( Autoloader::class, 'is_loaded' );
		$this->assertTrue( $property->getValue() );
		$this->assertTrue( Autoloader::autoload(), 'Autoload should return true on subsequent calls' );
	}

	/**
	 * Ensures missing autoloader notice registers both admin hooks.
	 */
	public function test_missing_autoloader_notice_adds_admin_notices(): void {
		$method = new \ReflectionMethod( Autoloader::class, 'missing_autoloader_notice' );
		$method->invoke( null );

		$this->expectOutputRegex( '/OneDesign: The Composer autoloader was not found./' );
		do_action( 'admin_notices' );

		$this->expectOutputRegex( '/OneDesign: The Composer autoloader was not found./' );
		do_action( 'network_admin_notices' );
	}

	/**
	 * Reset the Autoloader.
	 */
	private function reset_autoloader(): void {
		$property = new \ReflectionProperty( Autoloader::class, 'is_loaded' );
		$property->setValue( null, false );
	}
}
