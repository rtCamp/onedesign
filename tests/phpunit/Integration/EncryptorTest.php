<?php
/**
 * Encryptor unit tests.
 *
 * @package OneDesign\Tests\Integration
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration;

use OneDesign\Encryptor;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class EncryptorTest
 */
#[CoversClass( \OneDesign\Encryptor::class )]
class EncryptorTest extends TestCase {
	/**
	 * Test encrypt/decrypt roundtrip with various inputs.
	 *
	 * @param string $raw The raw input to encrypt then decrypt.
	 *
	 * @dataProvider roundtrip_provider
	 */
	#[DataProvider( 'roundtrip_provider' )]
	public function test_encrypt_decrypt_roundtrip( string $raw ): void {
		$encrypted = Encryptor::encrypt( $raw );

		$this->assertIsString( $encrypted );
		$this->assertNotSame( $raw, $encrypted );
		$this->assertSame( $raw, Encryptor::decrypt( $encrypted ) );
	}

	/**
	 * Test decrypt returns input unchanged when given invalid base64.
	 *
	 * @param string $invalid The invalid input string.
	 *
	 * @dataProvider invalid_input_provider
	 */
	#[DataProvider( 'invalid_input_provider' )]
	public function test_decrypt_returns_input_on_invalid_base64( string $invalid ): void {
		$this->assertSame( $invalid, Encryptor::decrypt( $invalid ) );
	}

	/**
	 * Test tampered ciphertext fails decryption.
	 */
	public function test_decrypt_returns_false_on_tampered_ciphertext(): void {
		$encrypted = Encryptor::encrypt( 'Sensitive data: ' . uniqid( '', true ) );
		$decoded   = base64_decode( $encrypted, true );

		$iv_length   = openssl_cipher_iv_length( 'aes-256-ctr' );
		$iv          = substr( $decoded, 0, $iv_length );
		$ciphertext  = substr( $decoded, $iv_length );
		$last_offset = strlen( $ciphertext ) - 1;

		$ciphertext[ $last_offset ] = 'A' === $ciphertext[ $last_offset ] ? 'B' : 'A';

		$this->assertFalse( Encryptor::decrypt( base64_encode( $iv . $ciphertext ) ) );
	}

	/**
	 * Provides input strings for roundtrip testing.
	 *
	 * @return array<string, array{string}>
	 */
	public static function roundtrip_provider(): array {
		return [
			'random string' => [ 'Sensitive data: ' . uniqid( '', true ) ],
			'unicode'       => [ 'こんにちは 👋 Привет مرحبا café' ],
			'empty string'  => [ '' ],
			'long string'   => [ str_repeat( 'OneDesign long string 12345 ', 500 ) ],
		];
	}

	/**
	 * Provides invalid inputs for decrypt passthrough testing.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_input_provider(): array {
		return [
			'not base64'  => [ 'not-valid-base64!' ],
			'bad padding' => [ '%%==' ],
			'plain text'  => [ 'plain text' ],
		];
	}
}
